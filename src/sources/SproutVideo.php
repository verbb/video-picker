<?php
namespace verbb\videopicker\sources;

use verbb\videopicker\base\CredentialsSource;
use verbb\videopicker\models\Collection;
use verbb\videopicker\models\Section;
use verbb\videopicker\models\Video;

use Craft;
use craft\helpers\App;
use craft\helpers\ArrayHelper;

use DateTime;
use Throwable;

class SproutVideo extends CredentialsSource
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('video-picker', 'Sprout Video');
    }


    // Properties
    // =========================================================================

    public static string $providerHandle = 'sproutVideo';

    public ?string $apiKey = null;


    // Public Methods
    // =========================================================================

    public function settingsAttributes(): array
    {
        $attributes = parent::settingsAttributes();
        $attributes[] = 'apiKey';

        return $attributes;
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['apiKey'], 'required', 'when' => function($model) {
                return $model->enabled;
            },
        ];

        return $rules;
    }

    public function isConfigured(): bool
    {
        return (bool)App::parseEnv($this->apiKey);
    }

    public function supportsSearch(): bool
    {
        return true;
    }

    public function getPrimaryColor(): ?string
    {
        return '#1E564F';
    }

    public function getIcon(): ?string
    {
        return '<svg fill="currentColor" viewBox="0 0 249.1 316.2"><path d="M28,191.3c0,52.5,42.5,95,95,95s94.8-42.3,95-94.6c-50.9-20.9-84.3-55.7-102.5-94.7,0,0,0-.3-.2-.4-48.8,3.9-87.3,44.8-87.3,94.7ZM99.7,155.4l61.1,40.5-61.1,41.2v-81.7Z"/><path d="M193.4,68.4c-4-4-8.6-7.3-13.3-11.3-19.3-15.3-34.5-22.6-47.8-43.8-3.3-4.6-6-9.3-10-13.3-5.6,21-6.2,43.9-1.4,66.3.5,2.4,1.1,4.8,1.8,7.3.3,1,.5,1.9.8,2.9.4,1.4.8,2.8,1.3,4.2,1.8,5.4,3.9,10.8,6.3,16,15,32.2,42.8,60.7,85.2,77.4.5.2,1,.4,1.6.6.8-20.6-5.3-41.6-17.7-62.6-3.2-5.3-6.8-10.7-10.8-16-.8-1.1-1.7-2.2-2.5-3.2-4.2-5.5-8.9-10.9-13.9-16.2-1.6-1.7-3.2-3.3-4.8-5,6.5,3.6,12.4,7.4,17.9,11.6,16.9,12.9,29.1,28.5,37,45.3q0,.1.1.2c12,25.4,14.4,53.6,9.2,79.8-2.7,16.8-9.1,32.4-18.4,45.8-20,28.7-53.2,47.5-90.8,47.5s-40.3-5.7-56.9-15.7c-32.2-19.3-53.7-54.6-53.7-94.9s3.5-31.8,9.9-45.8c9.9-25.6,30.4-48.2,63.3-62.5-3.2.9-6.3,1.8-9.4,2.8-16.2,5.5-29.7,13.4-40.4,22.9-16.4,16.3-28.2,37.2-33.3,60.6-1.8,8.3-2.7,16.8-2.7,25.6,0,25.5,7.8,49.1,21.2,68.6l2.4,3.6c1.7,2.3,3.5,4.5,5.4,6.6,22.3,26.1,55.3,42.6,92.3,42.6h1.6c29,0,55.7-9.9,76.9-26.5,11.8-9.5,22-20.9,30-33.8,1.4-2.4,2.8-4.8,4.1-7.2.6-1.1,1.1-2.2,1.7-3.4,1-2.1,2-4.2,2.9-6.3,22-52.5,11.3-119.6-45.1-170.7Z"/></svg>';
    }

    public function getCredentialsProviderConfig(): array
    {
        return [
            'base_uri' => 'https://api.sproutvideo.com/v1/',
            'headers' => [
                'SproutVideo-Api-Key' => App::parseEnv($this->apiKey),
            ],
        ];
    }

    public function getEmbedUrlFormat(): string
    {
        return 'https://videos.sproutvideo.com/embed/{id}';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        $patterns = [
            '/(?:https?:\/\/)?videos\.sproutvideo\.com\/embed\/([a-z0-9]+)(?:\/([a-z0-9]+))?/i',
            '/(?:https?:\/\/)?(?:www\.)?sproutvideo\.com\/videos\/([a-z0-9]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                $id = $matches[1];
                $token = $matches[2] ?? null;

                return $token ? $id . '/' . $token : $id;
            }
        }

        return null;
    }

    public function getVideoById(string $id): ?Video
    {
        $lookupId = $this->_apiVideoId($id);
        $data = $this->cachedRequest('GET', 'videos/' . rawurlencode($lookupId));

        if (is_array($data) && !empty($data['id'])) {
            return $this->_parseVideo($data);
        }

        return null;
    }


    // Protected Methods
    // =========================================================================

    protected function fetchExplorerSections(): array
    {
        $sections = [
            new Section([
                'name' => 'Library',
                'collections' => [
                    new Collection([
                        'name' => 'All Videos',
                        'method' => 'videos',
                        'icon' => 'video-camera',
                    ]),
                ],
            ]),
        ];

        $collections = [];

        foreach ($this->_getFolders() as $folder) {
            if (!is_array($folder)) {
                continue;
            }

            $folderId = $folder['id'] ?? null;

            if (!$folderId) {
                continue;
            }

            $collections[] = new Collection([
                'name' => $folder['name'] ?? ('Folder ' . $folderId),
                'method' => 'folder',
                'options' => ['id' => $folderId],
                'icon' => 'folder',
            ]);
        }

        if ($collections) {
            $sections[] = new Section([
                'name' => 'Folders',
                'collections' => $collections,
            ]);
        }

        return $sections;
    }

    protected function fetchVideosVideos(array $params = []): array
    {
        return $this->_performVideosRequest('videos', $params);
    }

    protected function fetchVideosFolder(array $params = []): array
    {
        $folderId = ArrayHelper::remove($params, 'id');

        if ($folderId) {
            $params['folder_id'] = $folderId;
        }

        return $this->_performVideosRequest('videos', $params);
    }

    protected function fetchVideosSearch(array $params = []): array
    {
        $search = trim((string)ArrayHelper::remove($params, 'q'));
        $result = $this->_performVideosRequest('videos', $params);

        if ($search === '') {
            return $result;
        }

        // Sprout has no keyword search — filter the current page by title.
        $result['videos'] = array_values(array_filter(
            $result['videos'],
            fn(Video $video) => stripos((string)$video->title, $search) !== false,
        ));

        return $result;
    }

    protected function pingCredentials(): void
    {
        $response = $this->request('GET', 'videos', [
            'query' => [
                'per_page' => 1,
            ],
        ]);

        $this->assertApiResponse($response, static::displayName(), fn(array $data) => array_key_exists('videos', $data));
    }


    // Private Methods
    // =========================================================================

    private function _performVideosRequest(string $uri, array $params = []): array
    {
        $query = $this->_queryFromParams($params);

        $response = $this->cachedRequest('GET', $uri, [
            'query' => $query,
        ]);

        $videos = [];

        foreach (($response['videos'] ?? []) as $videoData) {
            if (is_array($videoData)) {
                $videos[] = $this->_parseVideo($videoData);
            }
        }

        $perPage = $query['per_page'];
        $page = $query['page'];
        $total = (int)($response['total'] ?? 0);
        $nextPage = ($page * $perPage) < $total ? $page + 1 : null;

        return [
            'videos' => $videos,
            'nextPage' => $nextPage,
        ];
    }

    private function _parseVideo(array $data): Video
    {
        $video = new Video();
        $video->raw = $data;
        $video->sourceHandle = $this->handle;
        $video->id = $this->_videoId($data);
        $video->title = $data['title'] ?? null;
        $video->description = $this->_plainTextDescription($data['description'] ?? null);
        $video->authorName = $this->_folderName($data['folder_id'] ?? null) ?? $this->_accountName();
        $video->url = 'https://sproutvideo.com/videos/' . ($data['id'] ?? $this->_apiVideoId($video->id));
        $video->duration = isset($data['duration']) ? (int)round((float)$data['duration']) : null;
        $video->date = isset($data['created_at']) ? new DateTime($data['created_at']) : null;
        $video->plays = $data['plays'] ?? null;
        $video->width = $data['width'] ?? null;
        $video->height = $data['height'] ?? null;
        $video->private = (int)($data['privacy'] ?? 2) !== 2;

        $thumbnails = $data['assets']['thumbnails'] ?? [];

        if (is_array($thumbnails) && !empty($thumbnails[0])) {
            $video->thumbnails[] = ['url' => (string)$thumbnails[0]];
        }

        return $video;
    }

    private function _videoId(array $data): string
    {
        $id = (string)($data['id'] ?? '');
        $token = $data['security_token'] ?? null;

        if ($id === '') {
            return '';
        }

        if ($token && $token !== $id) {
            return $id . '/' . $token;
        }

        return $id;
    }

    private function _apiVideoId(string $id): string
    {
        return explode('/', $id, 2)[0];
    }

    private function _plainTextDescription(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text !== '' ? $text : null;
    }

    private function _getFolders(): array
    {
        $collections = [];
        $page = 1;

        do {
            $response = $this->cachedRequest('GET', 'folders', [
                'query' => [
                    'page' => $page++,
                    'per_page' => 50,
                ],
            ]);
            $items = $response['folders'] ?? [];
            $items = is_array($items) ? $items : [];
            array_push($collections, ...$items);
        } while (!empty($response['next_page']));

        return $collections;
    }

    private function _folderName(?string $folderId): ?string
    {
        if (!$folderId) {
            return null;
        }

        static $folders = [];

        $cacheKey = (string)($this->handle ?? 'sproutVideo');

        if (!isset($folders[$cacheKey])) {
            $folders[$cacheKey] = [];

            foreach ($this->_getFolders() as $folder) {
                if (is_array($folder) && !empty($folder['id'])) {
                    $folders[$cacheKey][$folder['id']] = $folder['name'] ?? null;
                }
            }
        }

        $name = trim((string)($folders[$cacheKey][$folderId] ?? ''));

        return $name !== '' ? $name : null;
    }

    private function _accountName(): ?string
    {
        static $names = [];

        $cacheKey = (string)($this->handle ?? 'sproutVideo');

        if (array_key_exists($cacheKey, $names)) {
            return $names[$cacheKey];
        }

        try {
            $response = $this->cachedRequest('GET', 'account');
            $names[$cacheKey] = $this->_parseAccountName($response);
        } catch (Throwable) {
            $names[$cacheKey] = null;
        }

        return $names[$cacheKey];
    }

    private function _parseAccountName(mixed $response): ?string
    {
        if (!is_array($response)) {
            return null;
        }

        $company = trim((string)($response['company'] ?? ''));

        if ($company !== '') {
            return $company;
        }

        $person = trim(trim((string)($response['first_name'] ?? '')) . ' ' . trim((string)($response['last_name'] ?? '')));

        if ($person !== '') {
            return $person;
        }

        $email = trim((string)($response['email'] ?? ''));

        return $email !== '' ? $email : null;
    }

    private function _queryFromParams(array $params = []): array
    {
        $page = (int)(ArrayHelper::remove($params, 'nextPage') ?? 1);

        ArrayHelper::remove($params, 'maxResults');
        ArrayHelper::remove($params, 'pageToken');
        ArrayHelper::remove($params, 'per_page');
        ArrayHelper::remove($params, 'page');

        return array_merge($params, [
            'page' => max(1, $page),
            'per_page' => $this->getVideosPerPage(),
        ]);
    }
}
