<?php
namespace verbb\videopicker\sources;

use verbb\videopicker\base\CredentialsSource;
use verbb\videopicker\models\Collection;
use verbb\videopicker\models\Section;
use verbb\videopicker\models\Video;

use Craft;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\UrlHelper;

use DateTime;
use Throwable;

class BunnyStream extends CredentialsSource
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('video-picker', 'Bunny Stream');
    }


    // Properties
    // =========================================================================

    public static string $providerHandle = 'bunnyStream';

    public ?string $libraryId = null;
    public ?string $streamApiKey = null;


    // Public Methods
    // =========================================================================

    public function settingsAttributes(): array
    {
        $attributes = parent::settingsAttributes();
        $attributes[] = 'libraryId';
        $attributes[] = 'streamApiKey';

        return $attributes;
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['libraryId', 'streamApiKey'], 'required', 'when' => function($model) {
                return $model->enabled;
            },
        ];

        return $rules;
    }

    public function isConfigured(): bool
    {
        return App::parseEnv($this->libraryId) && App::parseEnv($this->streamApiKey);
    }

    public function supportsSearch(): bool
    {
        return true;
    }

    public function getPrimaryColor(): ?string
    {
        return '#FF8451';
    }

    public function getIcon(): ?string
    {
        return '<svg fill="currentColor" viewBox="0 0 21.5 24"><path d="M11.7,3.8l5.6,3.1L12.2,0c-.9,1.1-1,2.6-.5,3.8h0ZM9.1,15c.7,0,1.3.6,1.3,1.3s-.6,1.2-1.3,1.2-1.3-.6-1.3-1.2c0-.7.6-1.3,1.3-1.3h0ZM5.2,1l15.8,8.4c.4.2.5.6.3,1,0,.2-.2.3-.3.3-1.2.7-2.5,1.2-3.9,1.5l-3.3,6.6s-1,2.3-3.9,1.4c1.2-1.2,2.6-2.3,2.6-4.1s-1.5-3.4-3.5-3.4-3.5,1.5-3.5,3.4,2.4,3.4,3.7,5c.6.8.5,2-.2,2.7-1.6-1.6-4.8-4.3-6.1-6-.7-.9-1.1-2-1.1-3.1.1-2.5,1.8-4.6,4.2-5.3.7-.2,1.5-.3,2.2-.3,1,0,2.1.4,3,.9,1.4.8,2.1.6,3-.2.6-.4,1.2-2,.2-2.3-.3-.1-.6-.2-1-.2-1.8-.3-4.9-.7-6.1-1.3-1.8-1-3.1-3.1-2.3-5.1h0ZM1.2,8.7c.6,0,1.2.5,1.2,1.2v1.2h-1.2c-.6,0-1.2-.5-1.2-1.2s.5-1.2,1.2-1.2Z"/></svg>';
    }

    public function getCredentialsProviderConfig(): array
    {
        return [
            'base_uri' => 'https://video.bunnycdn.com/',
            'headers' => [
                'AccessKey' => App::parseEnv($this->streamApiKey),
            ],
        ];
    }

    public function getEmbedUrlFormat(): string
    {
        $libraryId = App::parseEnv($this->libraryId) ?: '{libraryId}';

        return 'https://player.mediadelivery.net/embed/' . $libraryId . '/{id}';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        $patterns = [
            '/(?:https?:\/\/)?(?:player|iframe)\.mediadelivery\.net\/embed\/\d+\/([a-f0-9-]{36})/i',
            '/(?:https?:\/\/)?video\.bunnycdn\.com\/play\/\d+\/([a-f0-9-]{36})/i',
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
        $response = $this->cachedRequest('GET', $this->_videoPath($id));

        if (is_array($response) && !empty($response['guid'])) {
            return $this->_parseVideo($response);
        }

        return null;
    }


    // Protected Methods
    // =========================================================================

    protected function mapEmbedQueryParams(string $videoId, array $intent): array
    {
        $params = parent::mapEmbedQueryParams($videoId, $intent);

        // Bunny defaults autoplay to true and permits explicit per-embed overrides.
        foreach (['autoplay', 'loop'] as $key) {
            if (array_key_exists($key, $intent)) {
                $params[$key] = (int)$this->isEmbedTruthy($intent[$key]);
            }
        }

        if (array_key_exists('muted', $intent) || array_key_exists('mute', $intent)) {
            $params['muted'] = (int)$this->isEmbedTruthy($intent['muted'] ?? $intent['mute'] ?? null);
        }

        if (array_key_exists('start', $params)) {
            $params['t'] = $params['start'];
            unset($params['start']);
        }

        return $params;
    }

    protected function buildEmbedUrl(string $videoId, array $queryParams): string
    {
        $libraryId = App::parseEnv($this->libraryId);
        $url = 'https://player.mediadelivery.net/embed/' . rawurlencode((string)$libraryId) . '/' . rawurlencode($videoId);

        if ($queryParams) {
            $url = UrlHelper::urlWithParams($url, $queryParams);
        }

        return $url;
    }

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

        foreach ($this->_getCollections() as $collection) {
            if (!is_array($collection)) {
                continue;
            }

            $collectionId = $collection['guid'] ?? null;

            if (!$collectionId) {
                continue;
            }

            $collections[] = new Collection([
                'name' => $collection['name'] ?? ('Collection ' . $collectionId),
                'method' => 'collection',
                'options' => ['id' => $collectionId],
                'icon' => 'folder',
            ]);
        }

        if ($collections) {
            $sections[] = new Section([
                'name' => 'Collections',
                'collections' => $collections,
            ]);
        }

        return $sections;
    }

    protected function fetchVideosVideos(array $params = []): array
    {
        return $this->_performVideosRequest('library/' . $this->_libraryId() . '/videos', $params);
    }

    protected function fetchVideosCollection(array $params = []): array
    {
        $collectionId = ArrayHelper::remove($params, 'id');

        if ($collectionId) {
            $params['collection'] = $collectionId;
        }

        return $this->_performVideosRequest('library/' . $this->_libraryId() . '/videos', $params);
    }

    protected function fetchVideosSearch(array $params = []): array
    {
        $search = trim((string)ArrayHelper::remove($params, 'q'));

        if ($search !== '') {
            $params['search'] = $search;
        }

        return $this->_performVideosRequest('library/' . $this->_libraryId() . '/videos', $params);
    }

    protected function pingCredentials(): void
    {
        $response = $this->request('GET', 'library/' . $this->_libraryId() . '/videos', [
            'query' => [
                'page' => 1,
                'itemsPerPage' => 1,
            ],
        ]);

        $this->assertApiResponse($response, static::displayName(), fn(array $data) => array_key_exists('items', $data));
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

        foreach (($response['items'] ?? []) as $videoData) {
            if (is_array($videoData)) {
                $videos[] = $this->_parseVideo($videoData);
            }
        }

        $perPage = $query['itemsPerPage'];
        $page = $query['page'];
        $totalItems = (int)($response['totalItems'] ?? 0);
        $nextPage = ($page * $perPage) < $totalItems ? $page + 1 : null;

        return [
            'videos' => $videos,
            'nextPage' => $nextPage,
        ];
    }

    private function _parseVideo(array $data): Video
    {
        $guid = $data['guid'] ?? null;
        $libraryId = $data['videoLibraryId'] ?? App::parseEnv($this->libraryId);

        $video = new Video();
        $video->raw = $data;
        $video->sourceHandle = $this->handle;
        $video->id = $guid;
        $video->title = $data['title'] ?? ('Video ' . $guid);
        $video->description = $data['description'] ?? null;
        $video->authorName = $this->_collectionName($data['collectionId'] ?? null) ?? $this->_libraryLabel();
        $video->url = 'https://player.mediadelivery.net/embed/' . $libraryId . '/' . $guid;
        $video->duration = isset($data['length']) ? (int)$data['length'] : null;
        $video->date = $this->_parseDate($data['dateUploaded'] ?? null);
        $video->plays = $data['views'] ?? null;
        $video->width = $data['width'] ?? null;
        $video->height = $data['height'] ?? null;

        if ($thumbnail = ($data['thumbnailUrl'] ?? null)) {
            $video->thumbnails[] = ['url' => (string)$thumbnail];
        }

        return $video;
    }

    private function _getCollections(): array
    {
        $collections = [];
        $page = 1;

        do {
            $response = $this->cachedRequest('GET', 'library/' . $this->_libraryId() . '/collections', [
                'query' => [
                    'page' => $page++,
                    'itemsPerPage' => 50,
                ],
            ]);
            $items = $response['items'] ?? [];
            $items = is_array($items) ? $items : [];
            array_push($collections, ...$items);
        } while (!empty($items) && count($collections) < (int)($response['totalItems'] ?? 0));

        return $collections;
    }

    private function _collectionName(?string $collectionId): ?string
    {
        if (!$collectionId) {
            return null;
        }

        static $collections = [];

        $cacheKey = (string)($this->handle ?? 'bunnyStream');

        if (!isset($collections[$cacheKey])) {
            $collections[$cacheKey] = [];

            foreach ($this->_getCollections() as $collection) {
                if (is_array($collection) && !empty($collection['guid'])) {
                    $collections[$cacheKey][$collection['guid']] = $collection['name'] ?? null;
                }
            }
        }

        $name = trim((string)($collections[$cacheKey][$collectionId] ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * Stream API keys cannot read library metadata from the core API — label by library ID.
     */
    private function _libraryLabel(): string
    {
        $libraryId = trim((string)App::parseEnv($this->libraryId));

        return $libraryId !== '' ? ('Library ' . $libraryId) : static::displayName();
    }

    private function _libraryId(): string
    {
        return rawurlencode((string)App::parseEnv($this->libraryId));
    }

    private function _videoPath(string $videoId): string
    {
        return 'library/' . $this->_libraryId() . '/videos/' . rawurlencode($videoId);
    }

    private function _parseDate(mixed $value): ?DateTime
    {
        if ($value instanceof \DateTimeInterface) {
            return DateTime::createFromInterface($value);
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTime((string)$value);
        } catch (Throwable) {
            return null;
        }
    }

    private function _queryFromParams(array $params = []): array
    {
        $page = (int)(ArrayHelper::remove($params, 'nextPage') ?? 1);

        ArrayHelper::remove($params, 'maxResults');
        ArrayHelper::remove($params, 'pageToken');
        ArrayHelper::remove($params, 'per_page');
        ArrayHelper::remove($params, 'page');
        ArrayHelper::remove($params, 'itemsPerPage');

        return array_merge($params, [
            'page' => max(1, $page),
            'itemsPerPage' => $this->getVideosPerPage(),
        ]);
    }
}
