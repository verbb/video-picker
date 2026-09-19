<?php
namespace modules\videopickerscreenshots;

class ScreenshotYouTubeSource extends ScreenshotVimeoSource
{
    public static string $providerHandle = 'youTube';

    public static function displayName(): string
    {
        return 'YouTube';
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        return null;
    }
}
