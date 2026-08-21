<?php
namespace verbb\videopicker\services;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\helpers\EmbedImagesExtractor;
use verbb\videopicker\helpers\EmbedUrl;
use verbb\videopicker\models\Settings;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;

use Craft;
use craft\base\Component;
use craft\base\Field;
use craft\helpers\Json;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\helpers\UrlHelper;

use DateTime;

use Embed\Http\Crawler;
use Embed\Http\CurlClient;

use yii\caching\TagDependency;

use Throwable;

class Videos extends Component
{
    // Properties
    // =========================================================================

    /** @var array<string, Video|null> Request-local memo for normalize / repeated lookups. */
    private array $_videosByUrl = [];


    // Public Methods
    // =========================================================================

    /**
     * @param Field|null $field When a VideoPickerField, only match sources that allow that field.
     */
    public function getVideoByUrl(string $videoUrl, bool $clearCache = false, ?Field $field = null): ?Video
    {
        $memoKey = $videoUrl . "\0" . ($field instanceof VideoPickerField ? (string)$field->id : '*');

        if (!$clearCache && array_key_exists($memoKey, $this->_videosByUrl)) {
            return $this->_videosByUrl[$memoKey];
        }

        $allowedSources = VideoPicker::$plugin->getSources()->getSourcesForField($field);
        $allowedHandles = array_map(static fn(SourceInterface $source) => $source->handle, $allowedSources);

        // Fetch the video data from our saved database store of videos
        $record = VideoRecord::findOne([
            'videoUrl' => $videoUrl,
        ]);

        if ($record) {
            if ($clearCache) {
                $record->delete();

                // Also clear this source's local cache so the next fetch hits the provider
                foreach ($allowedSources as $source) {
                    if ($source->getVideoIdFromUrl($videoUrl)) {
                        $source->clearLocalCache();
                        break;
                    }
                }
            } else {
                // Handle emoji's in video content
                $video = new Video(Json::decode(StringHelper::shortcodesToEmoji($record->data)));

                // Hard-limit: cached rows from other providers must not leak into this field.
                if ($field instanceof VideoPickerField) {
                    if (
                        !$allowedHandles
                        || !$video->sourceHandle
                        || !in_array($video->sourceHandle, $allowedHandles, true)
                    ) {
                        return $this->_videosByUrl[$memoKey] = null;
                    }
                }

                return $this->_videosByUrl[$memoKey] = $video;
            }
        }

        // Fetch the video data from the source directly - we need to look through allowed
        // sources, as each defines their own logic for matching a URL pattern
        foreach ($allowedSources as $source) {
            if ($video = $source->getVideoByUrl($videoUrl)) {
                // Provider failures return a Video with errors — don't cache those.
                if (!$video->hasErrors()) {
                    $this->saveVideo($video);
                }

                return $this->_videosByUrl[$memoKey] = $video;
            }
        }

        return $this->_videosByUrl[$memoKey] = null;
    }

    public function saveVideo(Video $video): void
    {
        if (!$video->id || !$video->url || $video->hasErrors()) {
            return;
        }

        // Upsert by unique videoUrl (migration m260822_000000 adds the unique index).
        $record = VideoRecord::findOne([
            'videoUrl' => $video->url,
        ]) ?? new VideoRecord();

        $record->setAttributes([
            'videoId' => $video->id,
            'videoUrl' => $video->url,
            'data' => $video->serializeData(),
        ], false);

        $record->save();
    }

    public function getEmbedUrl(string $url, array $params = []): ?string
    {
        $html = $this->getEmbedHtml($url, $params);

        // Extract the embed URL from the iframe (single- or double-quoted src).
        if (!preg_match('/src=(["\'])([^"\']+)\1/', $html, $matches)) {
            return null;
        }

        $embedUrl = $matches[2] ?? '';

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

        try {
            EmbedUrl::assertAllowed($url, $settings->embedAllowedDomains);

            $cacheKey = 'video-picker:embed:' . md5(Json::encode([
                $url,
                $settings->embedAllowedDomains,
                $settings->embedClientSettings,
                $settings->embedDetectorsSettings,
                $settings->resolveHiResEmbedImage,
            ]));

            $cached = Craft::$app->getCache()->get($cacheKey);

            if ($cached !== false && is_array($cached)) {
                return $cached;
            }

            if (class_exists(CurlClient::class)) {
                // Handle Embed v4 support
                $client = new CurlClient();
                $clientSettings = array_merge([
                    'timeout' => 8,
                    'connect_timeout' => 5,
                    'max_redirs' => 3,
                ], $settings->embedClientSettings);
                $client->setSettings($clientSettings);

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

                $image = array_values($info->image ?? []);

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

                Craft::$app->getCache()->set(
                    $cacheKey,
                    $data,
                    max(60, (int)$settings->embedCacheDuration),
                    new TagDependency(['tags' => ['video-picker-embed']]),
                );

                return $data;
            }

            // Handle Embed v3 support
            $dispatcher = new \Embed\Http\CurlDispatcher($settings->embedClientSettings);

            $info = \Embed\Embed::create($url, $settings->getEmbedClientConfig(), $dispatcher);

            if (isset($info->url) && (string)$info->url !== '') {
                EmbedUrl::assertAllowed((string)$info->url, $settings->embedAllowedDomains);
            }

            $data = Json::decode(Json::encode([
                'title' => $info->title,
                'description' => $info->description,
                'url' => $info->url,
                'image' => $info->image,
                'code' => Template::raw($info->code ?: ''),
                'authorName' => $info->authorName,
                'authorUrl' => $info->authorUrl,
                'providerName' => $info->providerName,
                'providerUrl' => $info->providerUrl,
                'icon' => $info->providerIcon,
                'favicon' => $info->providerIcon,
                'publishedTime' => $info->publishedTime instanceof DateTime ? $info->publishedTime->format('c') : $info->publishedTime,
                'license' => $info->license,
                'feeds' => $info->feeds,
            ]));

            // If no embed code, create it
            if (!trim($data['code'])) {
                $data['code'] = '<iframe src="' . $info->url . '"></iframe>';
            }

            Craft::$app->getCache()->set(
                $cacheKey,
                $data,
                max(60, (int)$settings->embedCacheDuration),
                new TagDependency(['tags' => ['video-picker-embed']]),
            );

            return $data;
        } catch (Throwable $e) {
            $error = Craft::t('video-picker', 'Unable to fetch embed data for “{url}”: “{message}” {file}:{line}', [
                'url' => $url,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            VideoPicker::error($error);

            return ['error' => Craft::t('video-picker', 'Unable to fetch embed data for “{url}”: “{message}”', [
                'url' => $url,
                'message' => $e->getMessage(),
            ])];
        }
    }

}
