<?php
namespace verbb\videopicker\services;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\helpers\EmbedImagesExtractor;
use verbb\videopicker\models\Settings;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;

use Craft;
use craft\base\Component;
use craft\helpers\ArrayHelper;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\ConfigHelper;
use craft\helpers\Json;
use craft\helpers\Html;
use craft\helpers\StringHelper;
use craft\helpers\Template;
use craft\helpers\UrlHelper;

use DateTime;
use DateTimeZone;

use Embed\Http\Crawler;
use Embed\Http\CurlClient;

class Videos extends Component
{
    // Public Methods
    // =========================================================================

    public function getVideoByUrl(string $videoUrl, bool $clearCache = false): ?Video
    {
        // Fetch the video data from our saved database store of videos
        $record = VideoRecord::findOne([
            'videoUrl' => $videoUrl,
        ]);

        if ($record) {
            if ($clearCache) {
                $record->delete();

                // Also clear this source's local cache so the next fetch hits the provider
                foreach (VideoPicker::$plugin->getSources()->getAllEnabledSources() as $source) {
                    if ($source->getVideoIdFromUrl($videoUrl)) {
                        $source->clearLocalCache();
                        break;
                    }
                }
            } else {
                // Handle emoji's in video content
                return new Video(Json::decode(StringHelper::shortcodesToEmoji($record->data)));
            }
        }

        // Fetch the video data from the source directly - we need to look through all sources, as each defines
        // their own logic for matching a URL pattern
        foreach (VideoPicker::$plugin->getSources()->getAllEnabledSources() as $source) {
            if ($video = $source->getVideoByUrl($videoUrl)) {
                // Save it in our cache for next time
                $this->saveVideo($video);

                return $video;
            }
        }

        return null;
    }

    public function saveVideo(Video $video): void
    {
        if (!$video->id || !$video->url) {
            return;
        }

        // Create or update our video record
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

        // Extract the embed URL from the iframe. Different to the URL of the video
        preg_match('/src="([^"]+)"/', $html, $matches);
        $url = $matches[1] ?? '';

        if ($url) {
            return UrlHelper::urlWithParams($url, $params);
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
            if (class_exists(CurlClient::class)) {
                // Handle Embed v4 support
                $client = new CurlClient();
                $client->setSettings($settings->embedClientSettings);

                $crawler = new Crawler($client);
                $crawler->addDefaultHeaders($settings->embedHeaders);

                $embed = new \Embed\Embed($crawler);
                $embed->setSettings($settings->embedDetectorsSettings);

                // Override the image detector. Restores Embed v3 behaviour.
                $embed->getExtractorFactory()->addDetector('image', EmbedImagesExtractor::class);

                $info = $embed->get($url);
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

                return $data;
            } else {
                // Handle Embed v3 support
                $dispatcher = new \Embed\Http\CurlDispatcher($settings->embedClientSettings);

                $info = \Embed\Embed::create($url, $settings->getEmbedClientConfig(), $dispatcher);

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

                return $data;
            }
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

        return [];
    }

}
