<?php
namespace verbb\videopicker\sources;

use verbb\videopicker\base\CredentialsSource;
use verbb\videopicker\models\Collection;
use verbb\videopicker\models\Section;
use verbb\videopicker\models\Video;

use Craft;
use craft\helpers\App;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;

use DateTime;
use DateTimeInterface;
use Throwable;

class Dailymotion extends CredentialsSource
{
    // Constants
    // =========================================================================

    private const API_FIELDS = 'video_id,title,description,created_at,source.duration,source.width,source.height,thumbnail.h720_url,thumbnail.h480_url,profile.name,profile.display_name,profile.profile_id,visibility,video_url';

    private const OAUTH_TOKEN_URL = 'https://oauth2.dailymotion.com/v2/token';

    /** Read-only scopes for browsing videos and resolving profiles. */
    private const OAUTH_SCOPES = 'video.read profile.read account.read';


    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('video-picker', 'Dailymotion');
    }


    // Properties
    // =========================================================================

    public static string $providerHandle = 'dailymotion';

    public ?string $apiKey = null;
    public ?string $apiSecret = null;
    public ?string $channelUser = null;

    /** Request-local OAuth access token (Craft cache holds the durable copy). */
    private ?string $_accessToken = null;


    // Public Methods
    // =========================================================================

    public function settingsAttributes(): array
    {
        $attributes = parent::settingsAttributes();
        $attributes[] = 'apiKey';
        $attributes[] = 'apiSecret';
        $attributes[] = 'channelUser';

        return $attributes;
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['apiKey', 'apiSecret'], 'required', 'when' => function($model) {
                return $model->enabled;
            },
        ];

        return $rules;
    }

    public function isConfigured(): bool
    {
        return (bool)App::parseEnv($this->apiKey) && (bool)App::parseEnv($this->apiSecret);
    }

    public function supportsSearch(): bool
    {
        return true;
    }

    public function getPrimaryColor(): ?string
    {
        return '#0D0D0D';
    }

    public function getIcon(): ?string
    {
        return '<svg fill="currentColor" viewBox="0 0 21.6 24"><path d="M20.6,7.3c-.6-1.4-1.5-2.7-2.6-3.8-1.1-1.1-2.4-2-3.9-2.6C12.6.3,11,0,9.4,0H.6C.3,0,0,.3,0,.6c0,0,0,0,0,0v3.8C0,4.5,0,4.7.2,4.8l4.2,4.1c.1.1.3.2.4.2h4.6c.8,0,1.6.3,2.1.9.6.6.9,1.3.8,2.1,0,1.6-1.3,2.8-2.9,2.8H2.6c-.2,0-.3,0-.4.2-.1.1-.2.3-.2.4v3.8c0,.2,0,.3.2.4l4.1,4.1c.1.1.3.2.5.2h2.6c1.6,0,3.2-.3,4.7-.9,1.4-.6,2.8-1.5,3.9-2.6,1.1-1.1,2-2.4,2.6-3.8.6-1.5,1-3.1,1-4.7s-.3-3.2-1-4.7c0,0,0,0,0,0ZM1.2,4.1V2l2.9,2.8v2.3s-2.9-2.9-2.9-2.9ZM6.2,21.9l-2.9-2.9v-2.1l2.9,2.8s0,2.3,0,2.3ZM4.2,16.1h5.2c2.2,0,4.1-1.8,4.1-4,0-1.1-.4-2.2-1.2-2.9-.8-.8-1.9-1.2-3-1.2h-4v-2.7h4c1.8,0,3.6.7,4.8,2,2.6,2.6,2.7,6.9,0,9.5,0,0-.2.2-.2.2-1.3,1.2-3,1.8-4.7,1.8h-2.3l-2.9-2.7s0,0,0,0ZM19.5,16.2c-.6,1.3-1.3,2.5-2.3,3.4-1,1-2.2,1.8-3.5,2.3-1.3.6-2.8.9-4.3.8h-2v-2.8h1.9c2.1,0,4.2-.8,5.7-2.3,1.6-1.5,2.5-3.6,2.5-5.7,0-2.2-.9-4.2-2.5-5.7-1.5-1.5-3.5-2.3-5.7-2.3h-4.2L2.2,1.2h7.2c1.5,0,2.9.3,4.3.8,1.3.5,2.5,1.3,3.5,2.3,1,1,1.8,2.1,2.3,3.4.6,1.3.9,2.7.9,4.2s-.3,2.9-.9,4.2Z"/></svg>';
    }

    public function getCredentialsProviderConfig(): array
    {
        return [
            'base_uri' => 'https://api.dailymotion.com/v2/',
        ];
    }

    public function getCredentialsProviderOptions(array $options = []): array
    {
        $options['headers'] = array_merge($options['headers'] ?? [], [
            'Authorization' => 'Bearer ' . $this->_getAccessToken(),
        ]);

        return $options;
    }

    public function getEmbedUrlFormat(): string
    {
        return 'https://www.dailymotion.com/embed/video/{id}';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        $patterns = [
            '/(?:https?:\/\/)?(?:www\.)?dailymotion\.com\/video\/([a-zA-Z0-9]+)/i',
            '/(?:https?:\/\/)?(?:www\.)?dai\.ly\/([a-zA-Z0-9]+)/i',
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
        $data = $this->cachedRequest('GET', 'videos/' . $id, [
            'query' => [
                'fields' => self::API_FIELDS,
            ],
        ]);

        if (is_array($data) && !empty($data['video_id'])) {
            return $this->_parseVideo($data);
        }

        return null;
    }


    // Protected Methods
    // =========================================================================

    protected function fetchExplorerSections(): array
    {
        $sections = [];

        $collections = [];

        if (App::parseEnv($this->channelUser)) {
            $collections[] = new Collection([
                'name' => 'Channel Uploads',
                'method' => 'uploads',
                'icon' => 'video-camera',
            ]);
        }

        if ($collections) {
            $sections[] = new Section([
                'name' => 'Library',
                'collections' => $collections,
            ]);
        }

        return $sections;
    }

    protected function fetchVideosUploads(array $params = []): array
    {
        $profileId = $this->_resolveProfileId(App::parseEnv($this->channelUser));

        if (!$profileId) {
            return [
                'videos' => [],
                'nextPage' => null,
            ];
        }

        return $this->_performVideosRequest('profiles/' . $profileId . '/videos', $params);
    }

    protected function fetchVideosSearch(array $params = []): array
    {
        $search = trim((string)ArrayHelper::remove($params, 'q'));
        $profileId = $this->_resolveProfileId();

        if (!$profileId) {
            return [
                'videos' => [],
                'nextPage' => null,
            ];
        }

        $result = $this->_performVideosRequest('profiles/' . $profileId . '/videos', $params);

        if ($search === '') {
            return $result;
        }

        // API v2 has no global search endpoint — filter the current page by title.
        $result['videos'] = array_values(array_filter(
            $result['videos'],
            fn(Video $video) => stripos((string)$video->title, $search) !== false,
        ));

        return $result;
    }

    protected function pingCredentials(): void
    {
        $response = $this->request('GET', 'me', [
            'query' => [
                'fields' => 'user_id,profiles',
            ],
        ]);

        $this->assertApiResponse($response, static::displayName(), fn(array $data) => !empty($data['user_id']));
    }

    protected function extractApiErrorMessage(mixed $response): ?string
    {
        if (is_array($response) && !empty($response['error_description']) && is_string($response['error_description'])) {
            return $response['error_description'];
        }

        return parent::extractApiErrorMessage($response);
    }


    // Private Methods
    // =========================================================================

    private function _getAccessToken(): string
    {
        if ($this->_accessToken) {
            return $this->_accessToken;
        }

        $clientId = App::parseEnv($this->apiKey);
        $clientSecret = App::parseEnv($this->apiSecret);

        if (!$clientId || !$clientSecret) {
            throw new \Exception(Craft::t('video-picker', 'Dailymotion API key and secret are required.'));
        }

        $cacheKey = $this->_getAccessTokenCacheKey($clientId);
        $cached = Craft::$app->getCache()->get($cacheKey);

        if (is_array($cached) && !empty($cached['token']) && ($cached['expires'] ?? 0) > time()) {
            return $this->_accessToken = (string)$cached['token'];
        }

        try {
            $client = Craft::createGuzzleClient(['timeout' => 30]);
            $response = $client->post(self::OAUTH_TOKEN_URL, [
                'form_params' => [
                    'grant_type' => 'client_credentials',
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'scope' => self::OAUTH_SCOPES,
                ],
            ]);
        } catch (Throwable $e) {
            static::apiError($this, $e);
        }

        $body = (string)$response->getBody();

        try {
            $data = $body === '' ? [] : Json::decode($body);
        } catch (Throwable) {
            throw new \Exception(Craft::t('video-picker', 'Unexpected response from {provider}.', [
                'provider' => static::displayName(),
            ]));
        }

        if ($message = $this->extractApiErrorMessage($data)) {
            throw new \Exception(Craft::t('video-picker', '{provider} API error: {message}', [
                'provider' => static::displayName(),
                'message' => $message,
            ]));
        }

        if (empty($data['access_token'])) {
            throw new \Exception(Craft::t('video-picker', 'Unable to connect to {provider}. Check your credentials.', [
                'provider' => static::displayName(),
            ]));
        }

        $expiresIn = max(60, (int)($data['expires_in'] ?? 1800));
        $ttl = max(60, $expiresIn - 60);

        Craft::$app->getCache()->set($cacheKey, [
            'token' => $data['access_token'],
            'expires' => time() + $ttl,
        ], $ttl);

        return $this->_accessToken = (string)$data['access_token'];
    }

    private function _getAccessTokenCacheKey(string $clientId): string
    {
        return 'video-picker-dailymotion-token-' . md5(($this->handle ?? '') . ':' . $clientId);
    }

    /**
     * Resolve a profile ID from an explicit channel setting or the first manageable profile.
     */
    private function _resolveProfileId(?string $channel = null): ?string
    {
        $channel = trim((string)($channel ?? ''));

        $me = $this->cachedRequest('GET', 'me', [
            'query' => [
                'fields' => 'profiles',
            ],
        ]);

        $profiles = $me['profiles'] ?? [];

        if (!is_array($profiles) || $profiles === []) {
            return null;
        }

        if ($channel !== '') {
            if (preg_match('/^x[a-z0-9]+$/i', $channel)) {
                return $channel;
            }

            foreach ($profiles as $profile) {
                if (!is_array($profile)) {
                    continue;
                }

                $name = (string)($profile['name'] ?? '');

                if ($name !== '' && strcasecmp($name, $channel) === 0) {
                    return $profile['profile_id'] ?? null;
                }
            }

            return null;
        }

        $first = $profiles[0];

        return is_array($first) ? ($first['profile_id'] ?? null) : null;
    }

    private function _performVideosRequest(string $uri, array $params = []): array
    {
        $query = $this->_queryFromParams($params);
        $query['fields'] = self::API_FIELDS;

        $response = $this->cachedRequest('GET', $uri, [
            'query' => $query,
        ]);

        $videos = [];

        foreach (($response['data'] ?? []) as $videoData) {
            if (!is_array($videoData)) {
                continue;
            }

            $videos[] = $this->_parseVideo($videoData);
        }

        $pagination = is_array($response['pagination'] ?? null) ? $response['pagination'] : [];
        $hasNext = !empty($pagination['next']);

        return [
            'videos' => $videos,
            'nextPage' => $hasNext ? ($query['page'] + 1) : null,
        ];
    }

    private function _parseVideo(array $data): Video
    {
        $video = new Video();
        $video->raw = $data;
        $video->authorName = $data['profile']['display_name']
            ?? $data['profile']['name']
            ?? null;
        $video->date = $this->_parseDate($data['created_at'] ?? null);
        $video->description = $data['description'] ?? null;
        $video->sourceHandle = $this->handle;
        $video->id = $data['video_id'] ?? null;
        $video->title = $data['title'] ?? null;
        $video->url = $data['video_url'] ?? ('https://www.dailymotion.com/video/' . $video->id);
        $video->duration = $this->_parseDuration($data);
        $video->private = ($data['visibility'] ?? 'public') !== 'public';

        $thumbnail = $data['thumbnail']['h720_url']
            ?? $data['thumbnail']['h480_url']
            ?? null;

        if ($thumbnail) {
            $video->thumbnails[] = [
                'url' => $thumbnail,
                'width' => isset($data['source']['width']) ? (int)$data['source']['width'] : null,
                'height' => isset($data['source']['height']) ? (int)$data['source']['height'] : null,
            ];
        }

        return $video;
    }

    /**
     * API v2 stores duration on the nested `source` object, not the legacy top-level field.
     */
    private function _parseDuration(array $data): ?int
    {
        $duration = $data['source']['duration'] ?? $data['duration'] ?? null;

        if ($duration === null || $duration === '') {
            return null;
        }

        $seconds = (int)$duration;

        return $seconds > 0 ? $seconds : null;
    }

    private function _parseDate(mixed $value): ?DateTime
    {
        if ($value instanceof DateTimeInterface) {
            return DateTime::createFromInterface($value);
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        try {
            return new DateTime($value);
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
        ArrayHelper::remove($params, 'limit');

        return array_merge($params, [
            'page' => max(1, $page),
            'page_size' => $this->getVideosPerPage(),
        ]);
    }
}
