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

class Wistia extends CredentialsSource
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('video-picker', 'Wistia');
    }


    // Properties
    // =========================================================================

    public static string $providerHandle = 'wistia';

    public ?string $accessToken = null;


    // Public Methods
    // =========================================================================

    public function settingsAttributes(): array
    {
        $attributes = parent::settingsAttributes();
        $attributes[] = 'accessToken';

        return $attributes;
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['accessToken'], 'required', 'when' => function($model) {
                return $model->enabled;
            },
        ];

        return $rules;
    }

    public function isConfigured(): bool
    {
        return (bool)App::parseEnv($this->accessToken);
    }

    public function supportsSearch(): bool
    {
        return true;
    }

    public function getPrimaryColor(): ?string
    {
        return '#2949E5';
    }

    public function getIcon(): ?string
    {
        return '<svg fill="currentColor" viewBox="0 0 24 18.7"><path d="M23.7,3.9c.5-3.1-1.2-3.9-1.2-3.9,0,0,0,2.5-4.6,3.1-4.1.5-17.9.1-17.9.1l4.4,5.1c1.2,1.4,1.8,1.5,3.2,1.6,1.3,0,4.3,0,6.3,0,2.2-.2,5.4-.9,7.5-2.5,1.1-.8,2-2,2.2-3.3M24,7.1s-.6,1.1-3.3,2.9c-1.2.7-3.6,1.5-6.8,1.8-1.7.2-4.8,0-6.2,0s-2,.3-3.2,1.7L0,18.6s1.5,0,2.7,0,8.5.4,11.7-.5c10.5-2.9,9.5-11,9.5-11Z"/></svg>';
    }

    public function getCredentialsProviderConfig(): array
    {
        return [
            'base_uri' => 'https://api.wistia.com/v1/',
            'headers' => [
                'Authorization' => 'Bearer ' . App::parseEnv($this->accessToken),
            ],
        ];
    }

    public function getEmbedUrlFormat(): string
    {
        return 'https://fast.wistia.net/embed/iframe/{id}';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        $patterns = [
            '/(?:https?:\/\/)?(?:[\w.-]+\.)?wistia\.com\/medias\/([a-zA-Z0-9]+)/i',
            '/(?:https?:\/\/)?fast\.wistia\.net\/embed\/iframe\/([a-zA-Z0-9]+)/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    public function getVideoById(string $id): ?Video
    {
        $data = $this->cachedRequest('GET', 'medias/' . $id . '.json');

        if (is_array($data) && !empty($data['hashed_id'])) {
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
                        'name' => 'All Medias',
                        'method' => 'medias',
                        'icon' => 'video-camera',
                    ]),
                ],
            ]),
        ];

        $collections = [];

        foreach ($this->_getProjects() as $project) {
            if (!is_array($project)) {
                continue;
            }

            $projectId = $project['id'] ?? $project['hashed_id'] ?? null;

            if (!$projectId) {
                continue;
            }

            $collections[] = new Collection([
                'name' => $project['name'] ?? ('Project ' . $projectId),
                'method' => 'project',
                // v1 filters medias by numeric project_id (hashed_id is fallback for show URLs only).
                'options' => ['id' => $projectId],
                'icon' => 'folder',
            ]);
        }

        if ($collections) {
            $sections[] = new Section([
                'name' => 'Projects',
                'collections' => $collections,
            ]);
        }

        return $sections;
    }

    protected function fetchVideosMedias(array $params = []): array
    {
        return $this->_performVideosRequest('medias.json', $params);
    }

    protected function fetchVideosProject(array $params = []): array
    {
        $projectId = ArrayHelper::remove($params, 'id');

        if ($projectId) {
            // v1 has no nested projects/{id}/medias route — filter the medias list instead.
            $params['project_id'] = $projectId;
        }

        return $this->_performVideosRequest('medias.json', $params);
    }

    protected function fetchVideosSearch(array $params = []): array
    {
        $params['search'] = ArrayHelper::remove($params, 'q');

        return $this->_performVideosRequest('medias.json', $params);
    }

    protected function pingCredentials(): void
    {
        $response = $this->request('GET', 'medias.json', [
            'query' => [
                'per_page' => 1,
            ],
        ]);

        $this->assertApiResponse($response, static::displayName(), fn(array $data) => array_is_list($data) || isset($data[0]));
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
        $items = is_array($response) ? $response : [];

        foreach ($items as $videoData) {
            if (is_array($videoData)) {
                $videos[] = $this->_parseVideo($videoData);
            }
        }

        $perPage = $query['per_page'];
        $page = $query['page'];

        return [
            'videos' => $videos,
            'nextPage' => count($videos) >= $perPage ? $page + 1 : null,
        ];
    }

    private function _parseVideo(array $data): Video
    {
        $video = new Video();
        $video->raw = $data;
        $video->sourceHandle = $this->handle;
        $video->id = $data['hashed_id'] ?? null;
        $video->title = $data['name'] ?? null;
        $video->description = $this->_plainTextDescription($data['description'] ?? null);
        $video->url = $data['url'] ?? ('https://home.wistia.com/medias/' . $video->id);
        $video->duration = isset($data['duration']) ? (int)round((float)$data['duration']) : null;
        $video->date = isset($data['created']) ? new DateTime($data['created']) : null;
        $video->plays = $data['stats']['play_count'] ?? null;
        $video->width = $data['width'] ?? null;
        $video->height = $data['height'] ?? null;
        $video->private = !empty($data['password_protected']);

        $thumbnail = $data['thumbnail']['url'] ?? $data['thumbnail_url'] ?? null;

        if ($thumbnail) {
            $video->thumbnails[] = [
                'url' => $thumbnail,
                'width' => $data['thumbnail']['width'] ?? null,
                'height' => $data['thumbnail']['height'] ?? null,
            ];
        }

        $this->_applyAuthor($video, $data);

        return $video;
    }

    /**
     * Wistia has no uploader/channel — use the media's folder (project) or account name.
     */
    private function _applyAuthor(Video $video, array $data): void
    {
        $project = $data['project'] ?? $data['folder'] ?? null;

        if (is_array($project)) {
            $name = trim((string)($project['name'] ?? ''));
            $hashedId = $project['hashed_id'] ?? $project['hashedId'] ?? null;

            if ($name !== '') {
                $video->authorName = $name;
                $video->authorUrl = $hashedId
                    ? 'https://home.wistia.com/projects/' . $hashedId
                    : null;

                return;
            }
        }

        $account = $this->_accountDetails();

        if ($account['name'] !== '') {
            $video->authorName = $account['name'];
            $video->authorUrl = $account['url'] ?: null;
        }
    }

    /**
     * v1 descriptions are HTML; there is no plain-text API field — strip tags for safe preview text.
     */
    private function _plainTextDescription(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $text = trim(html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;

        return $text !== '' ? $text : null;
    }

    private function _accountDetails(): array
    {
        static $accounts = [];

        $cacheKey = (string)($this->handle ?? 'wistia');

        if (isset($accounts[$cacheKey])) {
            return $accounts[$cacheKey];
        }

        try {
            $response = $this->cachedRequest('GET', 'account.json');
            $accounts[$cacheKey] = [
                'name' => trim((string)($response['name'] ?? '')),
                'url' => trim((string)($response['url'] ?? '')),
            ];
        } catch (Throwable) {
            $accounts[$cacheKey] = ['name' => '', 'url' => ''];
        }

        return $accounts[$cacheKey];
    }

    private function _getProjects(): array
    {
        $collections = [];
        $page = 1;

        do {
            $response = $this->cachedRequest('GET', 'projects.json', [
                'query' => [
                    'page' => $page++,
                    'per_page' => 50,
                ],
            ]);
            $items = $response;
            $items = is_array($items) ? $items : [];
            array_push($collections, ...$items);
        } while (count($items) >= 50);

        return $collections;
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
