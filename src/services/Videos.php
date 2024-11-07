<?php
namespace verbb\videopicker\services;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\models\Settings;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;

use Craft;
use craft\base\Component;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\ConfigHelper;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

use DateTime;
use DateTimeZone;

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

}
