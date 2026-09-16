<?php
namespace verbb\videopicker\base;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Source as SourceRecord;

use Craft;
use craft\base\Field;
use craft\base\SavableComponent;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;

use verbb\auth\helpers\Provider as ProviderHelper;
use verbb\videopicker\fields\VideoPickerField;

use DateTime;
use Exception;
use Throwable;

use yii\caching\TagDependency;

use GuzzleHttp\Exception\RequestException;

abstract class Source extends SavableComponent implements SourceInterface
{
    // Constants
    // =========================================================================

    public const TYPE_CREDENTIALS = 'credentials';
    public const TYPE_OAUTH = 'oauth';

    public const CONNECT_SUCCESS = 'success';
    public const CONNECT_FAIL = 'fail';


    // Static Methods
    // =========================================================================

    public static function supportsConnection(): bool
    {
        return false;
    }

    public static function supportsOAuthConnection(): bool
    {
        return false;
    }

    public static function apiError($source, $exception, $throwError = true): void
    {
        $messageText = $exception->getMessage();

        // Check for Guzzle errors, which are truncated in the exception `getMessage()`.
        if ($exception instanceof RequestException && $exception->getResponse()) {
            $messageText = (string)$exception->getResponse()->getBody();
        }

        $message = Craft::t('video-picker', 'API error: “{message}” {file}:{line}', [
            'message' => $messageText,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        VideoPicker::error($source->name . ': ' . $message);

        if ($throwError) {
            throw new Exception($message);
        }
    }


    // Properties
    // =========================================================================

    public ?string $name = null;
    public ?string $handle = null;
    public ?bool $enabled = null;
    public ?int $sortOrder = null;
    public array $cache = [];
    public ?string $uid = null;

    /**
     * Fields this source is available on.
     * `*` / null = all Video Picker fields; `[]` = none; otherwise field UIDs.
     */
    public mixed $fields = '*';

    /** Request-local page size override from a field setting (D02 / S-F02). */
    private ?int $_videosPerPageOverride = null;


    // Public Methods
    // =========================================================================

    public function settingsAttributes(): array
    {
        $attributes = parent::settingsAttributes();
        $attributes[] = 'fields';

        return $attributes;
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['name', 'handle'], 'unique',
            'targetClass' => SourceRecord::class,
            'filter' => fn($query) => $query->andWhere(['not', ['id' => $this->id]]),
        ];
        $rules[] = [['id'], 'number', 'integerOnly' => true];

        $rules[] = [
            ['handle'],
            HandleValidator::class,
            'reservedWords' => [
                'dateCreated',
                'dateUpdated',
                'edit',
                'id',
                'title',
                'uid',
            ],
        ];

        return $rules;
    }

    /**
     * Whether this source may be used by the given Video Picker field.
     */
    public function isAvailableForField(?Field $field): bool
    {
        if (!$field instanceof VideoPickerField) {
            return true;
        }

        $fields = $this->fields;

        if ($fields === null || $fields === '*') {
            return true;
        }

        if ($fields === '' || $fields === []) {
            return false;
        }

        if (!is_array($fields) || !$field->uid) {
            return true;
        }

        return in_array($field->uid, $fields, true);
    }

    public function getProviderName(): string
    {
        return static::displayName();
    }

    public function getProviderHandle(): string
    {
        return static::$providerHandle;
    }

    public function getProviderDocsHandle(): string
    {
        return StringHelper::toKebabCase(static::$providerHandle);
    }

    public function getPrimaryColor(): ?string
    {
        return ProviderHelper::getPrimaryColor(static::$providerHandle);
    }

    public function getIcon(): ?string
    {
        return ProviderHelper::getIcon(static::$providerHandle);
    }

    public function getCpEditUrl(): ?string
    {
        return UrlHelper::cpUrl('video-picker/sources/' . $this->handle);
    }

    public function supportsBrowse(): bool
    {
        return false;
    }

    public function supportsSearch(): bool
    {
        return false;
    }

    public function isConnected(): bool
    {
        return false;
    }

    /**
     * Non-OAuth / missing sources default to not configured so CP templates stay safe.
     */
    public function isConfigured(): bool
    {
        return false;
    }

    public function isUsable(): bool
    {
        return $this->isConfigured() && (!$this->supportsConnection() || $this->isConnected());
    }

    public function checkConnection(bool $useCache = true): bool
    {
        return false;
    }

    /**
     * CP sidebar status: connected, disconnected, or error (credentials check failed).
     */
    public function getConnectionStatus(): string
    {
        if (!$this->supportsConnection()) {
            return $this->isConnected() ? 'connected' : 'disconnected';
        }

        if ($this::supportsOAuthConnection()) {
            return $this->isConnected() ? 'connected' : 'disconnected';
        }

        if (!$this->isConfigured()) {
            return 'disconnected';
        }

        $cached = $this->getConnectionCache();

        if ($cached === self::CONNECT_SUCCESS) {
            return 'connected';
        }

        if ($cached === self::CONNECT_FAIL) {
            return 'error';
        }

        return 'disconnected';
    }

    public function getSettingsHtml(): ?string
    {
        $handle = StringHelper::toKebabCase(static::$providerHandle);

        return Craft::$app->getView()->renderTemplate('video-picker/sources/_types/' . $handle . '/settings', [
            'source' => $this,
        ]);
    }

    /**
     * @param bool $includeSections When true, may hit the provider to refresh collections.
     *                            When false, return the persisted explorer cache only (no provider I/O).
     */
    public function getExplorerData(bool $clearCache = false, bool $includeSections = true): array
    {
        $explorerSections = $includeSections
            ? $this->getExplorerSections($clearCache)
            : $this->_getExplorerCache();

        return [
            'name' => $this->name,
            'handle' => $this->handle,
            'supportsBrowse' => $this->supportsBrowse(),
            'supportsSearch' => $this->supportsSearch(),
            // Lazy explorer: skip provider collection discovery unless this source is hydrated.
            'sections' => $explorerSections,
        ];
    }

    public function getExplorerSections(bool $clearCache = false): array
    {
        if ($clearCache) {
            $this->_setExplorerCache([]);

            // Clear the data cache as well for locally cached items
            TagDependency::invalidate(Craft::$app->getCache(), $this->_getLocalCacheTag());
            $this->_persistSourceCache();
        }

        $explorerCache = $this->_getExplorerCache();

        // Use the cache of explorer data, if available
        if ($explorerCache) {
            return $explorerCache;
        }

        $this->_setExplorerCache($this->fetchExplorerSections());
        $this->_persistSourceCache();

        return $this->_getExplorerCache();
    }

    public function getVideos(string $method, array $options = [], ?int $videosPerPage = null): array
    {
        // Explorer AJAX can send arbitrary option bags — only keep known keys, then
        // providers still force page size after merge (D02).
        $options = $this->filterVideoRequestOptions($options);
        $previousOverride = $this->_videosPerPageOverride;

        if ($videosPerPage !== null) {
            $this->_videosPerPageOverride = max(1, min(50, $videosPerPage));
        }

        try {
            $methodName = 'fetchVideos' . ucwords($method);

            if (method_exists($this, $methodName)) {
                return $this->{$methodName}($options);
            }

            return [];
        } finally {
            $this->_videosPerPageOverride = $previousOverride;
        }
    }

    /**
     * Keys clients may pass into get-videos (collection id, search, pagination).
     * Provider-specific API knobs (maxResults, part, etc.) are never client-set.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    protected function filterVideoRequestOptions(array $options): array
    {
        $allowed = array_flip(['id', 'q', 'nextPage']);

        return array_intersect_key($options, $allowed);
    }

    public function getVideoById(string $id): ?Video
    {
        return null;
    }

    public function getVideoByUrl(string $url): ?Video
    {
        try {
            if ($videoId = $this->getVideoIdFromUrl($url)) {
                return $this->getVideoById($videoId);
            }
        } catch (Throwable $e) {
            $video = new Video();
            $video->url = $url;
            $video->addError('url', $e->getMessage());

            return $video; 
        }

        return null;
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        return null;
    }

    public function getVideosPerPage(): int
    {
        if ($this->_videosPerPageOverride !== null) {
            return $this->_videosPerPageOverride;
        }

        // Clamp so callers / settings cannot mint oversized provider pages.
        return max(1, min(50, (int)VideoPicker::$plugin->getSettings()->videosPerPage));
    }

    public function getEmbedUrlFormat(): string
    {
        return '';
    }

    /**
     * Embed URL for a video id.
     *
     * `$options` may mix:
     * - **Intent** — `autoplay`, `muted`/`mute`, `loop`, `controls`, `start` (mapped per provider)
     * - **Iframe attrs** — `title`, `class`, `width`, `height`, `allow`, `loading`, …
     * - **Query passthrough** — anything else (e.g. `rel`, `theme`) appended as query params
     *
     * Provider-unsupported intent keys are ignored by `mapEmbedQueryParams()`.
     */
    public function getEmbedUrl(string $videoId, array $options = []): string
    {
        [$intent, $queryExtra] = $this->partitionEmbedOptions($options);

        return $this->buildEmbedUrl(
            $videoId,
            array_merge($this->mapEmbedQueryParams($videoId, $intent), $queryExtra)
        );
    }

    /**
     * Iframe HTML for a video id. Intent/query options shape `src`; iframe keys become attributes.
     */
    public function getEmbedHtml(string $videoId, array $options = []): string
    {
        [$intent, $queryExtra, $attrs] = $this->partitionEmbedOptions($options);

        $attributes = array_merge([
            'src' => $this->buildEmbedUrl(
                $videoId,
                array_merge($this->mapEmbedQueryParams($videoId, $intent), $queryExtra)
            ),
            'title' => 'External video from ' . $this->handle,
            'frameborder' => '0',
            'allowfullscreen' => 'true',
            'allow' => 'autoplay; encrypted-media',
        ], $attrs);

        return Html::tag('iframe', '', $attributes);
    }

    /**
     * Map neutral embed intent → provider query params. Override per source; ignore unsupported keys.
     *
     * @param array{autoplay?: mixed, muted?: mixed, mute?: mixed, loop?: mixed, controls?: mixed, start?: mixed} $intent
     */
    protected function mapEmbedQueryParams(string $videoId, array $intent): array
    {
        $params = [];

        if ($this->isEmbedTruthy($intent['autoplay'] ?? null)) {
            $params['autoplay'] = 1;
        }

        if ($this->isEmbedTruthy($intent['muted'] ?? $intent['mute'] ?? null)) {
            $params['muted'] = 1;
        }

        if ($this->isEmbedTruthy($intent['loop'] ?? null)) {
            $params['loop'] = 1;
        }

        // Only emit controls when explicitly off — providers default to on.
        if (array_key_exists('controls', $intent) && !$this->isEmbedTruthy($intent['controls'])) {
            $params['controls'] = 0;
        }

        if (isset($intent['start']) && $intent['start'] !== '' && $intent['start'] !== null) {
            $params['start'] = (int)$intent['start'];
        }

        return $params;
    }

    /**
     * @return array{0: array, 1: array, 2: array} intent, query passthrough, iframe attributes
     */
    protected function partitionEmbedOptions(array $options): array
    {
        $intentKeys = ['autoplay', 'muted', 'mute', 'loop', 'controls', 'start'];
        $attrKeys = [
            'title', 'class', 'id', 'width', 'height', 'style', 'allow', 'loading',
            'frameborder', 'allowfullscreen', 'referrerpolicy', 'sandbox', 'name',
        ];

        $intent = [];
        $queryExtra = [];
        $attrs = [];

        foreach ($options as $key => $value) {
            if (in_array($key, $intentKeys, true)) {
                $intent[$key] = $value;
            } elseif (in_array($key, $attrKeys, true)) {
                $attrs[$key] = $value;
            } else {
                // Legacy Twig: unknown keys were query params (and wrongly iframe attrs too).
                $queryExtra[$key] = $value;
            }
        }

        return [$intent, $queryExtra, $attrs];
    }

    protected function buildEmbedUrl(string $videoId, array $queryParams): string
    {
        $url = Craft::t('app', $this->getEmbedUrlFormat(), ['id' => $videoId]);

        if ($queryParams) {
            $url = UrlHelper::urlWithParams($url, $queryParams);
        }

        return $url;
    }

    protected function isEmbedTruthy(mixed $value): bool
    {
        if ($value === true || $value === 1 || $value === '1') {
            return true;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['true', 'yes', 'on'], true);
        }

        return false;
    }

    public function cachedRequest(string $method = 'GET', string $uri = '', array $options = [])
    {
        // Preserve URI/options case — only normalize the HTTP method (avoids search/ID collisions).
        $method = strtoupper($method);
        $cacheKey = $this->_buildLocalCacheKey($method, $uri, $options);

        // `false` is a miss; empty arrays are valid cached payloads.
        $cachedData = $this->_getLocalCache($cacheKey);

        if ($cachedData !== false) {
            return $cachedData;
        }

        $data = $this->request($method, $uri, $options);

        $this->_setLocalCache($cacheKey, $data, $this->_cacheDurationForRequest($uri, $options));

        return $data;
    }

    public function clearLocalCache(): void
    {
        TagDependency::invalidate(Craft::$app->getCache(), $this->_getLocalCacheTag());
    }

    /**
     * Drop persisted explorer sections + tagged provider application cache.
     * Call when credentials / account identity change so stale playlists don’t linger.
     */
    public function clearExplorerCache(): void
    {
        $this->_setExplorerCache([]);
        $this->_persistSourceCache();
        $this->clearLocalCache();
    }

    /**
     * Drop persisted credential connection status (Formie `cache.connection` pattern).
     */
    public function clearConnectionCache(): void
    {
        $this->_setConnectionCache(null);
        $this->_persistSourceCache();
        $this->_deleteLegacyConnectionCache();
    }


    // Protected Methods
    // =========================================================================

    protected function fetchExplorerSections(): array
    {
        return [];
    }

    protected function getConnectionCache(): ?string
    {
        $connection = $this->_getConnectionCache();

        return is_string($connection) ? $connection : null;
    }

    protected function setConnectionCache(string $status): void
    {
        $this->_setConnectionCache($status);
        $this->_persistSourceCache();
    }


    // Private Methods
    // =========================================================================

    private function _buildLocalCacheKey(string $method, string $uri, array $options): string
    {
        // Include source identity so reconnecting the same handle to another account
        // cannot reuse the previous account’s cached private collections/videos.
        $identity = [
            $this->uid ?: $this->handle,
            (string)$this->id,
            $this->_accountCacheGeneration(),
            $method,
            $uri,
            $options,
        ];

        return 'video-picker:req:' . md5(Json::encode($identity));
    }

    /**
     * Non-secret fingerprint of OAuth/config so credential changes bust the key.
     */
    private function _accountCacheGeneration(): string
    {
        $parts = [];

        if (property_exists($this, 'clientId') && $this->clientId) {
            $parts[] = (string)$this->clientId;
        }

        foreach (['accessToken', 'apiKey', 'apiSecret', 'tokenId', 'tokenSecret', 'channelUser'] as $attr) {
            if (property_exists($this, $attr) && $this->{$attr}) {
                $parts[] = md5((string)$this->{$attr});
            }
        }

        if (method_exists($this, 'getToken')) {
            $token = $this->getToken();
            if ($token && isset($token->id)) {
                $parts[] = (string)$token->id;
            }
        }

        return $parts ? md5(implode(':', $parts)) : '0';
    }

    private function _cacheDurationForRequest(string $uri, array $options): int
    {
        $settings = VideoPicker::$plugin->getSettings();
        $isSearch = isset($options['query']['q'])
            || isset($options['q'])
            || str_contains(strtolower($uri), 'search');

        return $isSearch
            ? max(60, (int)$settings->providerSearchCacheDuration)
            : max(60, (int)$settings->providerCacheDuration);
    }

    private function _getLocalCache(string $cacheKey): mixed
    {
        return Craft::$app->getCache()->get($cacheKey);
    }

    private function _setLocalCache(string $cacheKey, mixed $data, int $duration): void
    {
        Craft::$app->getCache()->set($cacheKey, $data, $duration, new TagDependency(['tags' => $this->_getLocalCacheTag()]));
    }

    private function _getLocalCacheTag(): string
    {
        // Prefer UID so renaming a handle doesn’t orphan the tag namespace.
        $id = $this->uid ?: $this->handle;

        return implode('__', ['video-picker', $id]);
    }

    private function _getConnectionCacheKey(): string
    {
        return 'video-picker:connection:' . ($this->uid ?: $this->handle);
    }

    /**
     * Normalize the persisted `cache` column (legacy explorer list → structured bag).
     */
    private function _normalizeSourceCache(): void
    {
        if (is_string($this->cache)) {
            $this->cache = Json::decode($this->cache) ?: [];
        }

        if (!is_array($this->cache)) {
            $this->cache = [];
        }

        // Legacy installs stored explorer sections as a root-level list.
        if ($this->cache !== [] && array_is_list($this->cache)) {
            $this->cache = [
                'explorer' => $this->cache,
                'connection' => null,
            ];
        }

        if (!array_key_exists('explorer', $this->cache) || !is_array($this->cache['explorer'])) {
            $this->cache['explorer'] = [];
        }

        if (!array_key_exists('connection', $this->cache)) {
            $this->cache['connection'] = null;
        }
    }

    private function _getExplorerCache(): array
    {
        $this->_normalizeSourceCache();

        return $this->cache['explorer'];
    }

    private function _setExplorerCache(array $sections): void
    {
        $this->_normalizeSourceCache();
        $this->cache['explorer'] = $sections;
    }

    private function _getConnectionCache(): ?string
    {
        $this->_normalizeSourceCache();
        $connection = $this->cache['connection'] ?? null;

        return is_string($connection) ? $connection : null;
    }

    private function _setConnectionCache(?string $status): void
    {
        $this->_normalizeSourceCache();
        $this->cache['connection'] = $status;
    }

    private function _persistSourceCache(): void
    {
        if (!$this->id) {
            return;
        }

        $this->_normalizeSourceCache();

        Db::update('{{%video_picker_sources}}', ['cache' => Json::encode($this->cache)], ['id' => $this->id]);
    }

    private function _deleteLegacyConnectionCache(): void
    {
        Craft::$app->getCache()->delete($this->_getConnectionCacheKey());
    }
}
