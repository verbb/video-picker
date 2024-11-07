<?php
namespace verbb\videopicker\variables;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\models\Video;

use Throwable;

class VideoPickerVariable
{
    // Public Methods
    // =========================================================================

    public function getPlugin(): VideoPicker
    {
        return VideoPicker::$plugin;
    }

    public function getPluginName(): string
    {
        return VideoPicker::$plugin->getPluginName();
    }

    public function getAllSources(): array
    {
        return VideoPicker::$plugin->getSources()->getAllSources();
    }

    public function getAllEnabledSources(): array
    {
        return VideoPicker::$plugin->getSources()->getAllEnabledSources();
    }

    public function getAllConfiguredSources(): array
    {
        return VideoPicker::$plugin->getSources()->getAllConfiguredSources();
    }

    public function getSourceById(int $id): ?SourceInterface
    {
        return VideoPicker::$plugin->getSources()->getSourceById($id);
    }

    public function getSourceByHandle(string $handle): ?SourceInterface
    {
        return VideoPicker::$plugin->getSources()->getSourceByHandle($handle);
    }

    public function getVideoByUrl(string $videoUrl, bool $clearCache = false): ?Video
    {
        try {
            return VideoPicker::$plugin->getVideos()->getVideoByUrl($videoUrl, $clearCache);
        } catch (Throwable $e) {
            VideoPicker::error('Couldn’t get video from its url (' . $videoUrl . '): ' . $e->getMessage());
        }

        return null;
    }
    
}