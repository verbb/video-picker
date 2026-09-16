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
use craft\helpers\Template;
use craft\helpers\UrlHelper;

use yii\caching\TagDependency;

use DateInterval;
use DateTime;
use Throwable;

use Embed\Http\Crawler;

class Videos extends Component
{
    // Constants
    // =========================================================================

    public const CACHE_OK = 'ok';
    public const CACHE_STALE = 'stale';
    public const CACHE_UNAVAILABLE = 'unavailable';

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
            return $this->_videosByUrl[$memoKey] = null;
        }

        // Fetch the video data from our saved database store of videos
        $record = VideoRecord::findOne([
            'videoUrl' => $videoUrl,
        ]);

        if ($record) {
            if ($clearCache) {
                // Explicit refresh: fetch first, then replace. Keep last-known-good if
                // the provider fails (DATA-04) — never delete the row up front.
                // Exception: field-scoped private snapshots must not leak across accounts.
                foreach ($allowedSources as $source) {
                    if ($source->getVideoIdFromUrl($videoUrl)) {
                        $source->clearLocalCache();
                        break;
                    }
                }

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
                        break;
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
        if (!$video->id || !$video->url || $video->hasErrors()) {
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
                'videoId' => $video->id,
                'videoUrl' => $video->url,
                'data' => $video->serializeData(),
                'fetchedAt' => $now,
                'expiresAt' => $expires,
                'status' => self::CACHE_OK,
                'lastError' => null,
            ], false);

            try {
                $record->save();
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

        if ($embedUrl) {
            return UrlHelper::urlWithParams($embedUrl, $params);
        }

        return null;
    }

    public function getEmbedHtml(string $url, array $params = []): string
    {
        $data = $this->getEmbedData($url);
        $html = $data['code'] ?? '';

        // Check if this contains an iframe already, if not - create one
        if (!str_contains($html, '<iframe')) {
            $src = htmlspecialchars('data:text/html,' . rawurlencode($html));
            $html = Html::tag('iframe', '', array_merge(['src' => $src, 'height' => 200], $params));
        } else {
            $html = Html::modifyTagAttributes($html, $params);
        }

        return $html;
    }

    public function getEmbedData(string $url): array
    {
        /* @var Settings $settings */
        $settings = VideoPicker::$plugin->getSettings();

        $cacheKey = $this->_embedCacheKey($url, $settings);
        $cached = Craft::$app->getCache()->get($cacheKey);

        // Success and short-lived error payloads share this key.
        if ($cached !== false && is_array($cached)) {
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
                    'code' => Template::raw($info->code ?: ''),
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

                // If no embed code, create it
                if (!trim($data['code'])) {
                    $data['code'] = '<iframe src="' . $info->url . '"></iframe>';
                }

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


    // Private Methods
    // =========================================================================

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
        foreach ($sources as $source) {
            if ($video = $source->getVideoByUrl($videoUrl)) {
                return $video;
            }
        }

        return null;
    }

    private function _videoFromRecord(VideoRecord $record): Video
    {
        // Handle emoji's in video content
        $video = new Video(Json::decode(StringHelper::shortcodesToEmoji($record->data)));
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
