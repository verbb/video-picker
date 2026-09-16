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

class Mux extends CredentialsSource
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('video-picker', 'Mux');
    }


    // Properties
    // =========================================================================

    public static string $providerHandle = 'mux';

    public ?string $tokenId = null;
    public ?string $tokenSecret = null;


    // Public Methods
    // =========================================================================

    public function settingsAttributes(): array
    {
        $attributes = parent::settingsAttributes();
        $attributes[] = 'tokenId';
        $attributes[] = 'tokenSecret';

        return $attributes;
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['tokenId', 'tokenSecret'], 'required', 'when' => function($model) {
                return $model->enabled;
            },
        ];

        return $rules;
    }

    public function isConfigured(): bool
    {
        return App::parseEnv($this->tokenId) && App::parseEnv($this->tokenSecret);
    }

    public function supportsSearch(): bool
    {
        return false;
    }

    public function getPrimaryColor(): ?string
    {
        return '#FF6101';
    }

    public function getIcon(): ?string
    {
        return '<svg fill="currentColor" viewBox="0 0 65.5 65"><path d="M60.4.6c-3.1-1.3-6.6-.6-8.9,1.8l-18.8,18.6L14,2.4C11.6,0,8.1-.6,5.1.6,2,1.9,0,4.8,0,8.1v48.7c0,4.5,3.7,8.1,8.2,8.1s8.2-3.6,8.2-8.1v-29.2l10.6,10.5c3.2,3.2,8.4,3.2,11.6,0l10.6-10.5v29.1c0,4.5,3.7,8.1,8.2,8.1s8.2-3.6,8.2-8.1V8.1c0-3.3-2-6.2-5-7.5h0ZM57.3,62.5c-3.1,0-5.6-2.5-5.6-5.6s2.5-5.6,5.6-5.6,5.6,2.5,5.6,5.6c0,3.1-2.5,5.6-5.6,5.6Z"/></svg>';
    }

    public function getCredentialsProviderConfig(): array
    {
        return [
            'base_uri' => 'https://api.mux.com/',
            'auth' => [
                App::parseEnv($this->tokenId),
                App::parseEnv($this->tokenSecret),
            ],
        ];
    }

    public function getEmbedUrlFormat(): string
    {
        return 'https://player.mux.com/{id}';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        $patterns = [
            '/(?:https?:\/\/)?(?:www\.)?player\.mux\.com\/([a-zA-Z0-9]+)/i',
            '/(?:https?:\/\/)?(?:www\.)?stream\.mux\.com\/([a-zA-Z0-9]+)/i',
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
        // IDs from browse are playback IDs; pasted URLs may be playback or asset IDs.
        if ($this->_looksLikeAssetId($id)) {
            $data = $this->cachedRequest('GET', 'video/v1/assets/' . $id);

            if (is_array($data) && !empty($data['data'])) {
                $playbackId = $this->_primaryPlaybackId($data['data']);

                return $this->_parseAsset($data['data'], $playbackId ?? $id);
            }

            return null;
        }

        try {
            $playback = $this->cachedRequest('GET', 'video/v1/playback-ids/' . $id);
            $assetId = $playback['data']['object']['id'] ?? $playback['data']['asset_id'] ?? null;

            if (!$assetId) {
                return null;
            }

            $asset = $this->cachedRequest('GET', 'video/v1/assets/' . $assetId);

            if (is_array($asset) && !empty($asset['data'])) {
                return $this->_parseAsset($asset['data'], $id);
            }
        } catch (Throwable) {
            // Fall through to direct asset lookup for UUID-shaped playback-less IDs.
            $data = $this->cachedRequest('GET', 'video/v1/assets/' . $id);

            if (is_array($data) && !empty($data['data'])) {
                $playbackId = $this->_primaryPlaybackId($data['data']);

                return $this->_parseAsset($data['data'], $playbackId ?? $id);
            }
        }

        return null;
    }


    // Protected Methods
    // =========================================================================

    protected function mapEmbedQueryParams(string $videoId, array $intent): array
    {
        $params = parent::mapEmbedQueryParams($videoId, $intent);

        if (array_key_exists('start', $params)) {
            $params['start-time'] = $params['start'];
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
                        'name' => 'Assets',
                        'method' => 'assets',
                        'icon' => 'video-camera',
                    ]),
                ],
            ]),
        ];
    }

    protected function fetchVideosAssets(array $params = []): array
    {
        $query = $this->_queryFromParams($params);

        $response = $this->cachedRequest('GET', 'video/v1/assets', [
            'query' => $query,
        ]);

        $videos = [];

        foreach (($response['data'] ?? []) as $asset) {
            $playbackId = $this->_primaryPlaybackId($asset);

            if ($playbackId) {
                $videos[] = $this->_parseAsset($asset, $playbackId);
            }
        }

        // Page-number pagination has no next-page URL. Count all assets, including
        // those without playback IDs, so an unpublished asset cannot hide later pages.
        $nextPage = count($response['data'] ?? []) >= $query['limit'] ? $query['page'] + 1 : null;

        return [
            'videos' => $videos,
            'nextPage' => $nextPage,
        ];
    }

    protected function pingCredentials(): void
    {
        $response = $this->request('GET', 'video/v1/assets', [
            'query' => [
                'limit' => 1,
            ],
        ]);

        $this->assertApiResponse($response, static::displayName(), fn(array $data) => array_key_exists('data', $data));
    }


    // Private Methods
    // =========================================================================

    private function _parseAsset(array $asset, string $playbackId): Video
    {
        $meta = $asset['meta'] ?? [];
        $passthrough = $asset['passthrough'] ?? null;

        $video = new Video();
        $video->raw = $asset;
        $video->sourceHandle = $this->handle;
        $video->id = $playbackId;
        $video->private = !$this->_playbackIdIsPublic($asset, $playbackId);
        $video->title = $meta['title'] ?? ($passthrough ?: ('Mux asset ' . ($asset['id'] ?? $playbackId)));
        $video->authorName = $meta['creator_id'] ?? $this->_organizationName();
        $video->url = 'https://player.mux.com/' . $playbackId;
        $video->duration = isset($asset['duration']) ? (int)round((float)$asset['duration']) : null;
        $video->date = $this->_parseDate($asset['created_at'] ?? null);

        if ($thumbnail = $this->_thumbnailUrl($playbackId, $asset)) {
            $video->thumbnails[] = ['url' => $thumbnail];
        }

        return $video;
    }

    /**
     * Mux serves thumbnails from image.mux.com using the playback ID (not asset meta).
     * Unsigned URLs only work for public playback policies.
     */
    private function _thumbnailUrl(string $playbackId, array $asset): ?string
    {
        $meta = $asset['meta'] ?? [];

        if (!empty($meta['thumbnail'])) {
            return (string)$meta['thumbnail'];
        }

        if (!$this->_playbackIdIsPublic($asset, $playbackId)) {
            return null;
        }

        return 'https://image.mux.com/' . rawurlencode($playbackId) . '/thumbnail.jpg?width=480';
    }

    private function _playbackIdIsPublic(array $asset, string $playbackId): bool
    {
        foreach (($asset['playback_ids'] ?? []) as $playback) {
            if (($playback['id'] ?? '') === $playbackId) {
                return ($playback['policy'] ?? '') === 'public';
            }
        }

        return false;
    }

    /**
     * Mux has no channel/profile concept — fall back to the token's organization name.
     */
    private function _organizationName(): ?string
    {
        static $names = [];

        $cacheKey = (string)($this->handle ?? 'mux');

        if (array_key_exists($cacheKey, $names)) {
            return $names[$cacheKey];
        }

        try {
            $response = $this->cachedRequest('GET', 'system/v1/whoami');
            $name = trim((string)($response['data']['organization_name'] ?? ''));
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

        // Mux returns Unix timestamps for some asset fields.
        if (is_numeric($value)) {
            return new DateTime('@' . (int)$value);
        }

        try {
            return new DateTime((string)$value);
        } catch (Throwable) {
            return null;
        }
    }

    private function _primaryPlaybackId(array $asset): ?string
    {
        foreach (($asset['playback_ids'] ?? []) as $playback) {
            if (($playback['policy'] ?? '') === 'public') {
                return $playback['id'] ?? null;
            }
        }

        return $asset['playback_ids'][0]['id'] ?? null;
    }

    private function _looksLikeAssetId(string $id): bool
    {
        return (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $id);
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
            'limit' => $this->getVideosPerPage(),
        ]);
    }
}
