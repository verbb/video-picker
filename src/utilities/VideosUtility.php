<?php
namespace verbb\videopicker\utilities;

use Craft;
use craft\base\Utility;
use craft\helpers\FileHelper;

class VideosUtility extends Utility
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('video-picker', 'Video Picker');
    }

    public static function id(): string
    {
        return 'video-picker';
    }

    public static function iconPath(): ?string
    {
        return Craft::getAlias('@vendor/verbb/video-picker/src/icon-mask.svg');
    }

    public static function contentHtml(): string
    {
        return Craft::$app->getView()->renderTemplate('video-picker/_utility');
    }
}
