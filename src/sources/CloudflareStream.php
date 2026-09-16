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

class CloudflareStream extends CredentialsSource
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('video-picker', 'Cloudflare Stream');
    }


    // Properties
    // =========================================================================

    public static string $providerHandle = 'cloudflareStream';

    public ?string $accountId = null;
    public ?string $apiToken = null;


    // Public Methods
    // =========================================================================

    public function settingsAttributes(): array
    {
        $attributes = parent::settingsAttributes();
        $attributes[] = 'accountId';
        $attributes[] = 'apiToken';

        return $attributes;
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['accountId', 'apiToken'], 'required', 'when' => function($model) {
                return $model->enabled;
            },
        ];

        return $rules;
    }

    public function isConfigured(): bool
    {
        return App::parseEnv($this->accountId) && App::parseEnv($this->apiToken);
    }

    public function supportsSearch(): bool
    {
        return true;
    }

    public function getPrimaryColor(): ?string
    {
        return '#F38020';
    }

    public function getIcon(): ?string
    {
        return '<svg fill="currentColor" viewBox="0 0 24 10.9"><path d="M16.5,10.3c.1-.5,0-1-.2-1.3-.2-.3-.6-.5-1.1-.5H6.6c0-.1-.1-.1-.1-.2,0,0,0,0,0-.2,0,0,.1-.1.2-.2h8.7c1-.2,2.2-1,2.6-2l.5-1.3c0,0,0-.1,0-.2-.6-2.5-2.8-4.4-5.5-4.4s-4.6,1.6-5.4,3.9c-.5-.4-1.1-.6-1.8-.5-1.2.1-2.2,1.1-2.3,2.3,0,.3,0,.6,0,.9-2,0-3.5,1.7-3.5,3.6s0,.4,0,.5c0,0,0,.1.2.1h16c0,0,.2,0,.2-.2v-.4h.1ZM19.3,4.7c0,0-.2,0-.2,0,0,0-.1,0-.1,0l-.3,1.2c-.1.5,0,1,.2,1.3.2.3.6.5,1.1.5h1.8c0,.1.1.1.1.2,0,0,0,.1,0,.2,0,0-.1.1-.2.2h-1.9c-1,.2-2.2,1-2.6,2v.4c-.2,0-.1.1,0,.1h6.6c0,0,.1,0,.2-.1.1-.4.2-.8.2-1.3,0-2.6-2.1-4.7-4.7-4.7"/></svg>';
    }

    public function getCredentialsProviderConfig(): array
    {
        return [
            'base_uri' => 'https://api.cloudflare.com/client/v4/',
            'headers' => [
                'Authorization' => 'Bearer ' . App::parseEnv($this->apiToken),
            ],
        ];
    }

    public function getEmbedUrlFormat(): string
    {
        return 'https://iframe.videodelivery.net/{id}';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        $patterns = [
            '/(?:https?:\/\/)?(?:[\w.-]+\.)?cloudflarestream\.com\/([a-f0-9]{32})/i',
            '/(?:https?:\/\/)?(?:www\.)?(?:iframe\.)?videodelivery\.net\/([a-f0-9]{32})/i',
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
        $response = $this->cachedRequest('GET', $this->_streamPath($id));

        if ($video = $this->_parseVideo($this->_unwrap($response))) {
            return $video;
        }

        return null;
    }


    // Protected Methods
    // =========================================================================

    protected function mapEmbedQueryParams(string $videoId, array $intent): array
    {
        $params = parent::mapEmbedQueryParams($videoId, $intent);

        if (array_key_exists('start', $params)) {
            $params['startTime'] = $params['start'];
            unset($params['start']);
        }

        return $params;
    }

    protected function fetchExplorerSections(): array
    {
        return [
            new Section([
                'name' => 'Library',
                'collections' => [
                    new Collection([
                        'name' => 'Videos',
                        'method' => 'videos',
                        'icon' => 'video-camera',
                    ]),
                ],
            ]),
        ];
    }

    protected function fetchVideosVideos(array $params = []): array
    {
        $query = $this->_queryFromParams($params);

        $response = $this->cachedRequest('GET', $this->_streamPath(), [
            'query' => $query,
        ]);

        $items = $this->_unwrap($response);
        $videos = [];

        if (is_array($items)) {
            foreach ($items as $item) {
                if (is_array($item) && ($video = $this->_parseVideo($item))) {
                    $videos[] = $video;
                }
            }
        }

        $perPage = $query['limit'];
        $nextPage = null;

        if (count($videos) >= $perPage) {
            $last = end($videos);
            $created = $last->raw['created'] ?? null;

            if ($created) {
                $nextPage = (string)$created;
            }
        }

        return [
            'videos' => $videos,
            'nextPage' => $nextPage,
        ];
    }

    protected function fetchVideosSearch(array $params = []): array
    {
        $search = trim((string)ArrayHelper::remove($params, 'q'));

        if ($search !== '') {
            $params['search'] = $search;
        }

        return $this->fetchVideosVideos($params);
    }

    protected function pingCredentials(): void
    {
        $response = $this->request('GET', $this->_streamPath(), [
            'query' => [
                'limit' => 1,
            ],
        ]);

        $this->assertApiResponse($response, static::displayName(), fn(array $data) => ($data['success'] ?? false) === true);
    }

    protected function extractApiErrorMessage(mixed $response): ?string
    {
        if (is_array($response) && !empty($response['errors']) && is_array($response['errors'])) {
            $messages = [];

            foreach ($response['errors'] as $error) {
                if (is_array($error) && !empty($error['message'])) {
                    $messages[] = (string)$error['message'];
                } elseif (is_string($error)) {
                    $messages[] = $error;
                }
            }

            if ($messages) {
                return implode(' ', $messages);
            }
        }

        return parent::extractApiErrorMessage($response);
    }


    // Private Methods
    // =========================================================================

    private function _streamPath(?string $videoId = null): string
    {
        $accountId = rawurlencode((string)App::parseEnv($this->accountId));
        $path = 'accounts/' . $accountId . '/stream';

        if ($videoId) {
            $path .= '/' . rawurlencode($videoId);
        }

        return $path;
    }

    private function _unwrap(mixed $response): mixed
    {
        if (!is_array($response) || !array_key_exists('success', $response)) {
            return $response;
        }

        if (($response['success'] ?? false) !== true) {
            $message = $this->extractApiErrorMessage($response)
                ?? Craft::t('video-picker', 'Unexpected response from {provider}.', ['provider' => static::displayName()]);

            throw new \Exception($message);
        }

        return $response['result'] ?? null;
    }

    private function _parseVideo(array $data): ?Video
    {
        $uid = $data['uid'] ?? null;

        if (!$uid) {
            return null;
        }

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];
        $preview = $data['preview'] ?? null;

        $video = new Video();
        $video->raw = $data;
        $video->sourceHandle = $this->handle;
        $video->id = $uid;
        $video->private = (bool)($data['requireSignedURLs'] ?? false);
        $video->title = $meta['name'] ?? ('Stream video ' . $uid);
        $video->description = $meta['description'] ?? null;
        $video->authorName = $this->_authorName($data);
        $video->authorUrl = $this->_authorUrl($data);
        $video->url = is_string($preview) && $preview !== ''
            ? $preview
            : ('https://iframe.videodelivery.net/' . $uid);
        $video->duration = isset($data['duration']) ? (int)round((float)$data['duration']) : null;
        $video->date = $this->_parseDate($data['created'] ?? null);

        if ($thumbnail = ($data['thumbnail'] ?? null)) {
            $video->thumbnails[] = ['url' => (string)$thumbnail];
        }

        return $video;
    }

    /**
     * Cloudflare exposes `creator` on the video object (a platform creator ID), not in `meta`.
     * Stream-only API tokens often cannot read `accounts/{id}`, so fall back to the provider name.
     */
    private function _authorName(array $data): string
    {
        $creator = trim((string)($data['creator'] ?? ''));

        if ($creator !== '') {
            return $creator;
        }

        $meta = is_array($data['meta'] ?? null) ? $data['meta'] : [];

        foreach (['creator', 'author'] as $key) {
            $value = trim((string)($meta[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return $this->_accountName() ?? static::displayName();
    }

    private function _authorUrl(array $data): ?string
    {
        $publicDetails = is_array($data['publicDetails'] ?? null) ? $data['publicDetails'] : [];
        $channelLink = trim((string)($publicDetails['channel_link'] ?? ''));

        return $channelLink !== '' ? $channelLink : null;
    }

    private function _accountName(): ?string
    {
        static $names = [];

        $cacheKey = (string)($this->handle ?? 'cloudflareStream');

        if (array_key_exists($cacheKey, $names)) {
            return $names[$cacheKey];
        }

        try {
            $accountId = App::parseEnv($this->accountId);
            $response = $this->cachedRequest('GET', 'accounts/' . rawurlencode((string)$accountId));
            $result = $this->_unwrap($response);
            $name = trim((string)($result['name'] ?? ''));
            $names[$cacheKey] = $name !== '' ? $name : null;
        } catch (Throwable) {
            $names[$cacheKey] = null;
        }

        return $names[$cacheKey];
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
        $before = ArrayHelper::remove($params, 'nextPage');

        ArrayHelper::remove($params, 'maxResults');
        ArrayHelper::remove($params, 'pageToken');
        ArrayHelper::remove($params, 'per_page');
        ArrayHelper::remove($params, 'page');
        ArrayHelper::remove($params, 'limit');

        $query = array_merge($params, [
            'limit' => $this->getVideosPerPage(),
        ]);

        if ($before) {
            $query['before'] = $before;
        }

        return $query;
    }
}
