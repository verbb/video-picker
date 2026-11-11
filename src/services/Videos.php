<?php
namespace verbb\videopicker\services;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\helpers\EmbedImagesExtractor;
use verbb\videopicker\helpers\EmbedUrl;
use verbb\videopicker\helpers\PinnedHttpClient;
use verbb\videopicker\models\Settings;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;

use Craft;
use craft\base\Component;
use craft\base\Field;
use craft\helpers\DateTimeHelper;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

use yii\caching\TagDependency;

use DateInterval;
use DateTime;
use DOMDocument;
use DOMElement;
use Throwable;

use Embed\Http\Crawler;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;

class Videos extends Component
{
    // Constants
    // =========================================================================

    public const CACHE_OK = 'ok';
    public const CACHE_STALE = 'stale';
    public const CACHE_UNAVAILABLE = 'unavailable';

    private const EMBED_IFRAME_ATTRIBUTES = [
        'allow',
        'allowfullscreen',
        'frameborder',
        'height',
        'loading',
        'referrerpolicy',
        'sandbox',
        'scrolling',
        'title',
        'width',
    ];

    /** Soft backoff after a failed revalidate so CP loads don’t hammer the API. */
    private const REVALIDATE_BACKOFF = 'PT15M';


    // Properties
    // =========================================================================

    /** Request-local memo for normalization and repeated lookups. */
    private array $_videosByUrl = [];


    // Public Methods
    // =========================================================================

    /** When given a Video Picker field, only match sources that allow that field. */
    public function getVideoByUrl(string $videoUrl, bool $clearCache = false, ?Field $field = null): ?Video
    {
        $memoKey = $videoUrl . "\0" . ($field instanceof VideoPickerField ? (string)$field->id : '*');

        if (!$clearCache && array_key_exists($memoKey, $this->_videosByUrl)) {
            return $this->_videosByUrl[$memoKey];
        }

        $allowedSources = VideoPicker::$plugin->getSources()->getSourcesForField($field);

        if ($field instanceof VideoPickerField && !$this->_compatibleSourcesForUrl($allowedSources, $videoUrl)) {
            // A provider with an enabled source stays restricted to that source. Only URLs
            // that no source handles at all fall back to a public generic embed.
            if ($this->_isUnhandledUrl($videoUrl)) {
                return $this->_videosByUrl[$memoKey] = $this->_getGenericVideoByUrl($videoUrl, $clearCache);
            }

            return $this->_videosByUrl[$memoKey] = null;
        }

        // Aliases may have no exact snapshot row, but Refresh must bypass provider caches.
        if ($clearCache) {
            foreach ($allowedSources as $source) {
                if ($source->getVideoIdFromUrl($videoUrl)) {
                    $source->clearLocalCache();
                }
            }
        }

        // Fetch the video data from our saved database store of videos
        $record = VideoRecord::findOne([
            'videoUrl' => $videoUrl,
        ]);

        // Generic snapshots only serve the field fallback above. Template lookups, and
        // URLs a source has since been added for, resolve through configured sources.
        if ($record && $this->_videoFromRecord($record)->generic) {
            $record = null;
        }

        if ($record) {
            if ($clearCache) {
                // Explicit refresh: fetch first, then replace. Keep last-known-good if
                // the provider fails (DATA-04) — never delete the row up front.
                // Exception: field-scoped private snapshots must not leak across accounts.
                $compatible = $field instanceof VideoPickerField
                    ? $this->_compatibleSourcesForUrl($allowedSources, $videoUrl)
                    : $allowedSources;
                $revalidateSources = $compatible ?: $allowedSources;
                $fresh = $this->_fetchLiveVideo($videoUrl, $revalidateSources);
                $previous = $this->_videoFromRecord($record);

                if ($fresh && !$fresh->hasErrors()) {
                    $this->saveVideo($fresh);

                    if ($field instanceof VideoPickerField) {
                        $fresh->sourceHandle = $this->_preferredSourceHandle(
                            $this->_compatibleSourcesForUrl($allowedSources, $videoUrl) ?: $allowedSources,
                            $fresh->sourceHandle,
                        );
                    }

                    $fresh->cacheStatus = self::CACHE_OK;

                    return $this->_videosByUrl[$memoKey] = $fresh;
                }

                $status = ($fresh && $fresh->hasErrors())
                    ? self::CACHE_UNAVAILABLE
                    : self::CACHE_STALE;
                $error = $fresh && $fresh->hasErrors()
                    ? implode(' ', $fresh->getFirstErrors())
                    : Craft::t('video-picker', 'Unable to refresh video metadata.');

                $this->_markRevalidateFailure($record, $status, $error);

                // Private rows are account-sensitive — do not hand another source's
                // snapshot to this field when live revalidate failed.
                if ($field instanceof VideoPickerField && $previous->private) {
                    return $this->_videosByUrl[$memoKey] = $this->_privateCacheMiss($videoUrl, $error, $status);
                }

                $previous->cacheStatus = $status;
                $previous->cacheError = $error;

                if ($field instanceof VideoPickerField) {
                    $previous->sourceHandle = $this->_preferredSourceHandle(
                        $compatible ?: $allowedSources,
                        $previous->sourceHandle,
                    );
                }

                return $this->_videosByUrl[$memoKey] = $previous;
            }

            $compatible = $field instanceof VideoPickerField
                ? $this->_compatibleSourcesForUrl($allowedSources, $videoUrl)
                : $allowedSources;

            // Field scoped: wrong provider for this field — fall through to live fetch.
            if ($field instanceof VideoPickerField && !$compatible) {
                // Leave the shared URL row alone; try a live resolve for this field.
            } else {
                $video = $this->_videoFromRecord($record);

                if ($field instanceof VideoPickerField) {
                    $video->sourceHandle = $this->_preferredSourceHandle($compatible, $video->sourceHandle);
                }

                $fieldScopedPrivate = $field instanceof VideoPickerField && $video->private;

                // Public + fresh: shared URL cache is fine. Private + field-scoped:
                // always revalidate with this field's sources (account trust boundary).
                if (!$this->_recordIsExpired($record) && !$fieldScopedPrivate) {
                    return $this->_videosByUrl[$memoKey] = $video;
                }

                // Expired (or private field-scoped) — sync revalidate.
                $revalidateSources = $compatible ?: $allowedSources;

                foreach ($revalidateSources as $source) {
                    if ($source->getVideoIdFromUrl($videoUrl)) {
                        // Bust provider app cache so revalidate isn’t served from TTL API cache.
                        $source->clearLocalCache();
                    }
                }

                $fresh = $this->_fetchLiveVideo($videoUrl, $revalidateSources);

                if ($fresh && !$fresh->hasErrors()) {
                    $this->saveVideo($fresh);

                    if ($field instanceof VideoPickerField) {
                        $fresh->sourceHandle = $this->_preferredSourceHandle(
                            $this->_compatibleSourcesForUrl($allowedSources, $videoUrl) ?: $allowedSources,
                            $fresh->sourceHandle,
                        );
                    }

                    $fresh->cacheStatus = self::CACHE_OK;

                    return $this->_videosByUrl[$memoKey] = $fresh;
                }

                $status = ($fresh && $fresh->hasErrors())
                    ? self::CACHE_UNAVAILABLE
                    : self::CACHE_STALE;
                $error = $fresh && $fresh->hasErrors()
                    ? implode(' ', $fresh->getFirstErrors())
                    : Craft::t('video-picker', 'Unable to refresh video metadata.');

                $this->_markRevalidateFailure($record, $status, $error);

                if ($fieldScopedPrivate) {
                    return $this->_videosByUrl[$memoKey] = $this->_privateCacheMiss($videoUrl, $error, $status);
                }

                $video->cacheStatus = $status;
                $video->cacheError = $error;

                return $this->_videosByUrl[$memoKey] = $video;
            }
        }

        // Miss / cleared / incompatible cached provider — live fetch.
        $video = $this->_fetchLiveVideo($videoUrl, $allowedSources);

        if ($video) {
            if (!$video->hasErrors()) {
                $this->saveVideo($video);
                $video->cacheStatus = self::CACHE_OK;
            }

            return $this->_videosByUrl[$memoKey] = $video;
        }

        return $this->_videosByUrl[$memoKey] = null;
    }

    public function saveVideo(Video $video, bool $replaceExisting = true): void
    {
        // Generic embeds have no provider id, so their URL identifies the snapshot.
        if ((!$video->id && !$video->generic) || !$video->url || $video->hasErrors()) {
            return;
        }

        // Upsert by unique videoUrl. Retry once on unique-constraint races (DATA-04).
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $record = VideoRecord::findOne([
                'videoUrl' => $video->url,
            ]) ?? new VideoRecord();

            if (!$replaceExisting && !$record->getIsNewRecord()) {
                return;
            }

            $now = new DateTime();
            $ttl = max(3600, (int)VideoPicker::$plugin->getSettings()->videoCacheDuration);
            $expires = (clone $now)->add(new DateInterval('PT' . $ttl . 'S'));

            $record->setAttributes([
                'videoId' => $video->id ?? $video->url,
                'videoUrl' => $video->url,
                'data' => $video->serializeData(),
                'dataVersion' => 1,
                'fetchedAt' => $now,
                'expiresAt' => $expires,
                'status' => self::CACHE_OK,
                'lastError' => null,
            ], false);

            try {
                $record->save();
                // Other URL aliases and field contexts may have memoized this snapshot.
                $this->_videosByUrl = [];

                return;
            } catch (Throwable $e) {
                // Concurrent insert against the unique index — reload and overwrite.
                if ($attempt === 0) {
                    continue;
                }

                throw $e;
            }
        }
    }

    public function getEmbedUrl(string $url, array $params = []): ?string
    {
        $html = $this->getEmbedHtml($url, $params);

        // Extract the embed URL from the iframe (single- or double-quoted src).
        if (!preg_match('/src=(["\'])([^"\']+)\1/', $html, $matches)) {
            return null;
        }

        $embedUrl = html_entity_decode($matches[2] ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // A non-iframe provider widget is isolated in a sandboxed data document
        // by getEmbedHtml(). Returning that URL alone would drop the sandbox and
        // let callers accidentally execute the provider's markup.
        if ($embedUrl && !str_starts_with(strtolower(trim($embedUrl)), 'data:')) {
            return UrlHelper::urlWithParams($embedUrl, $params);
        }

        return null;
    }

    /** Update source references without treating a rename as a metadata refresh. */
    public function renameSourceHandle(string $previousHandle, string $handle): void
    {
        $query = VideoRecord::find()
            ->select(['id', 'data'])
            ->where(['like', 'data', $previousHandle])
            ->asArray();

        // Batch snapshots to keep source edits bounded even for a large library.
        foreach ($query->batch(100) as $rows) {
            foreach ($rows as $row) {
                $data = Json::decode($row['data']);

                if (($data['sourceHandle'] ?? null) !== $previousHandle) {
                    continue;
                }

                $data['sourceHandle'] = $handle;

                // Do not replace metadata that a concurrent provider refresh wrote.
                Craft::$app->getDb()->createCommand()->update(
                    VideoRecord::tableName(),
                    ['data' => Json::encode($data, 0)],
                    ['id' => $row['id'], 'data' => $row['data']],
                )->execute();
            }
        }

        $this->_videosByUrl = [];
    }

    public function getEmbedHtml(string $url, array $params = []): string
    {
        $data = $this->getEmbedData($url);
        $baseUrl = (string)($data['url'] ?? $url);

        // getEmbedData() has already reduced remote markup to one safe iframe.
        return $this->_canonicalizeEmbedCode((string)($data['code'] ?? ''), $baseUrl, $params, true);
    }

    public function getEmbedData(string $url): array
    {
        /* @var Settings $settings */
        $settings = VideoPicker::$plugin->getSettings();

        $cacheKey = $this->_embedCacheKey($url, $settings);
        $cached = Craft::$app->getCache()->get($cacheKey);

        // Success and short-lived error payloads share this key.
        if ($cached !== false && is_array($cached)) {
            if (isset($cached['code'])) {
                // Old cache entries may predate canonicalization. Sanitize every
                // read so a stale payload cannot bypass the current boundary.
                $cached['code'] = $this->_canonicalizeEmbedCode(
                    (string)$cached['code'],
                    (string)($cached['url'] ?? $url),
                    [],
                    true,
                );
            }

            return $cached;
        }

        try {
            EmbedUrl::assertAllowed($url, $settings->embedAllowedDomains);

            if (class_exists(Crawler::class)) {
                // Handle Embed v4 support
                $clientSettings = array_merge([
                    'timeout' => 8,
                    'connect_timeout' => 5,
                    'max_redirs' => 3,
                ], $settings->embedClientSettings);
                // Force hop-by-hop validation — do not let client settings re-enable auto-follow.
                $clientSettings['follow_location'] = false;
                $client = new PinnedHttpClient($settings->embedAllowedDomains, $clientSettings);

                $crawler = new Crawler($client);
                $crawler->addDefaultHeaders($settings->embedHeaders);

                $embed = new \Embed\Embed($crawler);
                $embed->setSettings($settings->embedDetectorsSettings);

                // Override the image detector. Restores Embed v3 behaviour.
                $embed->getExtractorFactory()->addDetector('image', EmbedImagesExtractor::class);

                $info = $embed->get($url);

                // Re-check final URI after redirects against the same policy.
                if (isset($info->url) && (string)$info->url !== '') {
                    EmbedUrl::assertAllowed((string)$info->url, $settings->embedAllowedDomains);
                }

                $image = $info->image ?? [];

                $data = Json::decode(Json::encode([
                    'title' => $info->title,
                    'description' => $info->description,
                    'url' => $info->url,
                    'code' => (string)($info->code ?: ''),
                    'authorName' => $info->authorName,
                    'authorUrl' => $info->authorUrl,
                    'providerName' => $info->providerName,
                    'providerUrl' => $info->providerUrl,
                    'icon' => $info->icon,
                    'favicon' => $info->favicon,
                    'publishedTime' => $info->publishedTime instanceof DateTime ? $info->publishedTime->format('c') : $info->publishedTime,
                    'license' => $info->license,
                    'feeds' => $info->feeds,

                    // Images will always be an array to handle if we are fetching image metadata
                    ...$image,
                ]));

                // Field fallbacks only accept player markup the provider supplied.
                $data['hasProviderCode'] = trim($data['code']) !== '';

                // If no embed code, create it
                if (!trim($data['code'])) {
                    $data['code'] = '<iframe src="' . $info->url . '"></iframe>';
                }

                $data['code'] = $this->_canonicalizeEmbedCode(
                    (string)$data['code'],
                    (string)($data['url'] ?? $url),
                );

                $this->_storeEmbedCache($cacheKey, $data, max(60, (int)$settings->embedCacheDuration));

                return $data;
            }

            throw new \RuntimeException('Secure generic embeds require Embed 4.');
        } catch (Throwable $e) {
            $error = Craft::t('video-picker', 'Unable to fetch embed data for “{url}”: “{message}” {file}:{line}', [
                'url' => $url,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            VideoPicker::error($error);

            $payload = ['error' => Craft::t('video-picker', 'Unable to fetch embed data for “{url}”: “{message}”', [
                'url' => $url,
                'message' => $e->getMessage(),
            ])];

            // Short negative TTL — avoids hammering dead URLs every Twig render (D06).
            $errorTtl = max(0, (int)$settings->embedErrorCacheDuration);

            if ($errorTtl > 0) {
                $this->_storeEmbedCache($cacheKey, $payload, $errorTtl);
            }

            return $payload;
        }
    }

    /**
     * Player markup for a field video resolved through the generic embed path. `$params`
     * accepts iframe attributes only, since provider playback options have no mapping.
     */
    public function getGenericVideoEmbedHtml(Video $video, array $params = []): ?string
    {
        $attributes = $this->_genericVideoEmbedAttributes($video);

        if (!$attributes) {
            return null;
        }

        return Html::tag('iframe', '', array_merge($attributes, $this->_embedIframeAttributes($params)));
    }

    /** Player URL for a generic field video, with `$params` appended as query parameters. */
    public function getGenericVideoEmbedUrl(Video $video, array $params = []): ?string
    {
        $src = $this->_genericVideoEmbedAttributes($video)['src'] ?? null;

        return $src ? UrlHelper::urlWithParams($src, $params) : null;
    }


    // Private Methods
    // =========================================================================

    /** Reduce remote embed markup to one reconstructed iframe. */
    private function _canonicalizeEmbedCode(
        string $html,
        string $baseUrl,
        array $params = [],
        bool $allowSanitizedDataUrl = false,
    ): string {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<!doctype html><html><body>' . $html . '</body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $iframe = $document->getElementsByTagName('iframe')->item(0);

        if (!$iframe instanceof DOMElement) {
            // Preserve non-iframe provider widgets in an opaque, script-disabled
            // document rather than inserting their markup into the parent page.
            return Html::tag('iframe', '', array_merge([
                'src' => 'data:text/html;charset=utf-8,' . rawurlencode($html),
                'height' => 200,
                'sandbox' => '',
            ], $this->_embedIframeAttributes($params)));
        }

        $src = html_entity_decode(trim($iframe->getAttribute('src')), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (
            $allowSanitizedDataUrl
            && str_starts_with($src, 'data:text/html;charset=utf-8,')
            && $iframe->hasAttribute('sandbox')
        ) {
            $attributes = ['src' => $src, 'sandbox' => ''];
        } else {
            $src = $this->_resolveEmbedIframeUrl($src, $baseUrl);

            if ($src === null) {
                return '';
            }

            $attributes = ['src' => $src];
        }

        foreach (self::EMBED_IFRAME_ATTRIBUTES as $attribute) {
            if ($attribute === 'sandbox' && isset($attributes['sandbox'])) {
                continue;
            }

            if (!$iframe->hasAttribute($attribute)) {
                continue;
            }

            $attributes[$attribute] = $attribute === 'allowfullscreen'
                ? true
                : $iframe->getAttribute($attribute);
        }

        return Html::tag('iframe', '', array_merge(
            $attributes,
            $this->_embedIframeAttributes($params),
        ));
    }

    /** Keep only iframe presentation attributes; src and executable attributes are never caller-overridable. */
    private function _embedIframeAttributes(array $attributes): array
    {
        $safe = [];

        foreach ($attributes as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                continue;
            }

            $name = strtolower($name);
            $isPresentationAttribute = in_array($name, ['class', 'id', 'name', 'style'], true)
                || str_starts_with($name, 'data-')
                || str_starts_with($name, 'aria-');

            if (
                $name === 'sandbox'
                || (!in_array($name, self::EMBED_IFRAME_ATTRIBUTES, true) && !$isPresentationAttribute)
            ) {
                continue;
            }

            $safe[$name] = $value;
        }

        return $safe;
    }

    private function _resolveEmbedIframeUrl(string $url, string $baseUrl): ?string
    {
        if ($url === '') {
            return null;
        }

        try {
            $uri = new Uri($url);

            if ($uri->getScheme() === '') {
                $uri = UriResolver::resolve(new Uri($baseUrl), $uri);
            }

            $resolved = (string)$uri;
            EmbedUrl::assertAllowed($resolved);

            return $resolved;
        } catch (Throwable) {
            return null;
        }
    }

    private function _embedCacheKey(string $url, Settings $settings): string
    {
        return 'video-picker:embed:' . md5(Json::encode([
            $url,
            $settings->embedAllowedDomains,
            $settings->embedClientSettings,
            $settings->embedDetectorsSettings,
            $settings->resolveHiResEmbedImage,
        ]));
    }

    private function _storeEmbedCache(string $cacheKey, array $data, int $duration): void
    {
        Craft::$app->getCache()->set(
            $cacheKey,
            $data,
            $duration,
            new TagDependency(['tags' => ['video-picker-embed']]),
        );
    }

    private function _fetchLiveVideo(string $videoUrl, array $sources): ?Video
    {
        $firstError = null;

        foreach ($sources as $source) {
            if ($video = $source->getVideoByUrl($videoUrl)) {
                // A matching URL may belong to a later allowed account. Keep the
                // first error for recovery only if none of those accounts can resolve it.
                if ($video->hasErrors()) {
                    $firstError ??= $video;

                    continue;
                }

                return $video;
            }
        }

        return $firstError;
    }

    private function _videoFromRecord(VideoRecord $record): Video
    {
        // Existing snapshots used shortcode encoding; new JSON is already lossless.
        $data = (int)$record->dataVersion === 0
            ? StringHelper::shortcodesToEmoji($record->data)
            : $record->data;
        $video = new Video(Json::decode($data));
        $video->cacheStatus = $record->status ?: self::CACHE_OK;
        $video->cacheError = $record->lastError;

        return $video;
    }

    /**
     * Field-scoped miss for a private shared-cache row — URL only, no foreign metadata.
     */
    private function _privateCacheMiss(string $videoUrl, string $error, string $status): Video
    {
        $video = new Video(['url' => $videoUrl]);
        $video->addError('url', $error !== ''
            ? $error
            : Craft::t('video-picker', 'Unable to verify this private video for the current field’s sources.'));
        $video->cacheStatus = $status;
        $video->cacheError = $error;

        return $video;
    }

    private function _recordIsExpired(VideoRecord $record): bool
    {
        if (!$record->expiresAt) {
            return true;
        }

        $expires = DateTimeHelper::toDateTime($record->expiresAt);

        if (!$expires) {
            return true;
        }

        return $expires <= new DateTime();
    }

    private function _markRevalidateFailure(VideoRecord $record, string $status, string $error): void
    {
        $record->status = $status;
        $record->lastError = StringHelper::truncate($error, 1000);
        // Short backoff — stay expired relative to full TTL, but avoid per-request refetch.
        $record->expiresAt = (new DateTime())->add(new DateInterval(self::REVALIDATE_BACKOFF));
        $record->save(false);
    }

    /** True when no enabled source, connected or not, recognises the URL's provider. */
    private function _isUnhandledUrl(string $videoUrl): bool
    {
        return !$this->_compatibleSourcesForUrl(
            VideoPicker::$plugin->getSources()->getAllEnabledSources(),
            $videoUrl,
        );
    }

    /**
     * Resolve a URL no source handles through the generic embed path, keeping the
     * snapshot in the shared video cache with the same expiry as provider videos.
     */
    private function _getGenericVideoByUrl(string $videoUrl, bool $clearCache): Video
    {
        // The video cache keys rows by URL in a 255-character column.
        if (mb_strlen($videoUrl) > 255) {
            $video = new Video(['url' => $videoUrl]);
            $video->addError('url', Craft::t('video-picker', 'Unable to find the video.'));

            return $video;
        }

        $record = VideoRecord::findOne([
            'videoUrl' => $videoUrl,
        ]);

        $previous = $record ? $this->_videoFromRecord($record) : null;

        // A row from a removed or disabled source may hold another account's private
        // metadata. Only generic rows are safe to serve without that source.
        if ($previous && !$previous->generic) {
            $previous = null;
        }

        if ($previous && !$clearCache && !$this->_recordIsExpired($record)) {
            return $previous;
        }

        $fresh = $this->_fetchGenericVideo($videoUrl, $clearCache || $previous !== null);

        if (!$fresh->hasErrors()) {
            $this->saveVideo($fresh);
            $fresh->cacheStatus = self::CACHE_OK;

            return $fresh;
        }

        if (!$previous) {
            return $fresh;
        }

        // Keep the last good snapshot so a provider outage doesn't block saving content.
        $error = (string)$fresh->getFirstError('url');
        $this->_markRevalidateFailure($record, self::CACHE_UNAVAILABLE, $error);
        $previous->cacheStatus = self::CACHE_UNAVAILABLE;
        $previous->cacheError = $error;

        return $previous;
    }

    private function _fetchGenericVideo(string $videoUrl, bool $bypassCache): Video
    {
        /* @var Settings $settings */
        $settings = VideoPicker::$plugin->getSettings();
        $cacheKey = $this->_embedCacheKey($videoUrl, $settings);

        if ($bypassCache) {
            Craft::$app->getCache()->delete($cacheKey);
        }

        // getEmbedData() applies the host policy, pinned client and iframe canonicalization.
        $data = $this->getEmbedData($videoUrl);

        // Payloads cached before the provider-markup flag existed can't be judged. Fetch once.
        if (!isset($data['error']) && !array_key_exists('hasProviderCode', $data)) {
            Craft::$app->getCache()->delete($cacheKey);
            $data = $this->getEmbedData($videoUrl);
        }

        $embedAttributes = isset($data['error']) ? null : $this->_playableEmbedAttributes($data);

        // Short links, aliases and redirects that land on a provider with an enabled
        // source must go through that source and its field restrictions instead.
        $resolvesToSource = $embedAttributes && (
            !$this->_isUnhandledUrl($embedAttributes['src'])
            || (is_string($data['url'] ?? null) && !$this->_isUnhandledUrl($data['url']))
        );

        if (!$embedAttributes || $resolvesToSource) {
            $video = new Video(['url' => $videoUrl]);
            $video->addError('url', Craft::t('video-picker', 'Unable to find the video.'));

            return $video;
        }

        $image = $this->_httpUrl($data['image'] ?? null);
        $thumbnail = array_filter([
            'url' => $image,
            'width' => is_numeric($data['imageWidth'] ?? null) ? (int)$data['imageWidth'] : null,
            'height' => is_numeric($data['imageHeight'] ?? null) ? (int)$data['imageHeight'] : null,
        ], static fn($value) => $value !== null);

        $publishedTime = $this->_stringOrNull($data['publishedTime'] ?? null);

        return new Video([
            'url' => $videoUrl,
            'generic' => true,
            'title' => $this->_stringOrNull($data['title'] ?? null),
            'description' => $this->_stringOrNull($data['description'] ?? null),
            'authorName' => $this->_stringOrNull($data['authorName'] ?? null),
            // Remote page metadata becomes CP link targets, so only http(s) URLs are kept.
            'authorUrl' => $this->_httpUrl($data['authorUrl'] ?? null),
            'date' => $publishedTime ? (DateTimeHelper::toDateTime($publishedTime) ?: null) : null,
            'thumbnails' => $image ? [$thumbnail] : [],
            'raw' => array_filter([
                'providerName' => $this->_stringOrNull($data['providerName'] ?? null),
                'providerUrl' => $this->_httpUrl($data['providerUrl'] ?? null),
                // Rendering uses this snapshot, so the player survives provider outages
                // and pages don't refetch the remote page.
                'embedAttributes' => $embedAttributes,
            ]),
        ]);
    }

    /** Stored iframe attributes, re-checked so an edited snapshot can't inject markup. */
    private function _genericVideoEmbedAttributes(Video $video): ?array
    {
        $stored = $video->generic ? ($video->raw['embedAttributes'] ?? null) : null;

        if (!is_array($stored) || !($src = $this->_httpUrl($stored['src'] ?? null))) {
            return null;
        }

        $attributes = ['src' => $src];

        foreach (self::EMBED_IFRAME_ATTRIBUTES as $attribute) {
            $value = $stored[$attribute] ?? null;

            if ($attribute === 'sandbox' || !is_scalar($value)) {
                continue;
            }

            $attributes[$attribute] = $attribute === 'allowfullscreen' ? true : (string)$value;
        }

        return $attributes;
    }

    /**
     * Attributes of the provider-supplied iframe after canonicalization, or null for
     * synthesized page frames and sandboxed widgets.
     */
    private function _playableEmbedAttributes(array $data): ?array
    {
        $code = trim((string)($data['code'] ?? ''));

        if (empty($data['hasProviderCode']) || !str_starts_with($code, '<iframe')) {
            return null;
        }

        $document = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $document->loadHTML(
                '<!doctype html><html><body>' . $code . '</body></html>',
                LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        $iframe = $document->getElementsByTagName('iframe')->item(0);

        if (!$iframe instanceof DOMElement || $iframe->hasAttribute('sandbox')) {
            return null;
        }

        $src = $this->_httpUrl($iframe->getAttribute('src'));

        if (!$src) {
            return null;
        }

        $attributes = ['src' => $src];

        foreach (self::EMBED_IFRAME_ATTRIBUTES as $attribute) {
            if ($attribute === 'sandbox' || !$iframe->hasAttribute($attribute)) {
                continue;
            }

            $attributes[$attribute] = $attribute === 'allowfullscreen' ? true : $iframe->getAttribute($attribute);
        }

        return $attributes;
    }

    private function _httpUrl(mixed $value): ?string
    {
        $value = $this->_stringOrNull($value);

        if ($value === null || !filter_var($value, FILTER_VALIDATE_URL)) {
            return null;
        }

        return in_array(strtolower((string)parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true) ? $value : null;
    }

    private function _stringOrNull(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? $value : null;
    }

    /**
     * Sources that can parse this URL using the same provider grammar as a live fetch.
     */
    private function _compatibleSourcesForUrl(array $sources, string $videoUrl): array
    {
        return array_values(array_filter(
            $sources,
            static fn(SourceInterface $source) => (bool)$source->getVideoIdFromUrl($videoUrl),
        ));
    }

    /**
     * Keep the cached stamp when that source is still allowed; otherwise use the first compatible source.
     */
    private function _preferredSourceHandle(array $compatible, ?string $cachedHandle): string
    {
        if ($cachedHandle) {
            foreach ($compatible as $source) {
                if ($source->handle === $cachedHandle) {
                    return $cachedHandle;
                }
            }
        }

        return $compatible[0]->handle;
    }

}
