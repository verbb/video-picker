<?php
namespace verbb\videopicker\helpers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\web\assets\field\VideoPickerAsset;

class Plugin
{
    // Static Methods
    // =========================================================================

    public static function registerAsset(string $path): void
    {
        $viteService = VideoPicker::$plugin->getVite();

        $scriptOptions = [
            'depends' => [
                VideoPickerAsset::class,
            ],
            'onload' => '',
        ];

        $styleOptions = [
            'depends' => [
                VideoPickerAsset::class,
            ],
        ];

        $viteService->register($path, false, $scriptOptions, $styleOptions);

        // Provide nice build errors - only in dev
        if ($viteService->devServerRunning()) {
            $viteService->register('@vite/client', false);
        }
    }

}
