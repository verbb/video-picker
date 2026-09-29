<?php
namespace verbb\videopicker\helpers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\web\assets\cp\CpAsset;
use verbb\videopicker\web\assets\field\VideoPickerAsset as FieldVideoPickerAsset;
use verbb\videopicker\web\assets\sourceconnect\SourceConnectAsset;

use Craft;

class Plugin
{
    // Static Methods
    // =========================================================================

    public static function registerFieldAsset(string $path): void
    {
        $viteService = VideoPicker::$plugin->getVite();

        $scriptOptions = [
            'depends' => [
                FieldVideoPickerAsset::class,
            ],
            'onload' => '',
        ];

        $styleOptions = [
            'depends' => [
                FieldVideoPickerAsset::class,
            ],
        ];

        $viteService->register($path, false, $scriptOptions, $styleOptions);

        if ($viteService->devServerRunning()) {
            $viteService->register('@vite/client', false);
        }
    }

    public static function registerFieldAssets(): void
    {
        self::registerFieldAsset('field/src/js/plugin-kit-register.ts');
        self::registerFieldAsset('field/src/js/video-picker.ts');
    }

    public static function registerCpAsset(string $path): void
    {
        $viteService = VideoPicker::$plugin->getCpAssets();

        $scriptOptions = [
            'depends' => [
                CpAsset::class,
            ],
            'onload' => '',
        ];

        $styleOptions = [
            'depends' => [
                CpAsset::class,
            ],
        ];

        $viteService->register($path, false, $scriptOptions, $styleOptions);

        if ($viteService->devServerRunning()) {
            $viteService->register('@vite/client', false);
        }
    }

    /** CP shell styles (sources index, settings, provider badges). */
    public static function registerCpStyles(): void
    {
        self::registerCpAsset('src/video-picker-cp.js');
    }

    /** Source edit credential Connect / Refresh (Plugin Kit WCs + jQuery). */
    public static function registerSourceConnectAsset(string $path): void
    {
        $viteService = VideoPicker::$plugin->getSourceConnectAssets();

        $scriptOptions = [
            'depends' => [
                SourceConnectAsset::class,
            ],
            'onload' => '',
        ];

        $styleOptions = [
            'depends' => [
                SourceConnectAsset::class,
            ],
        ];

        $viteService->register($path, false, $scriptOptions, $styleOptions);

        if ($viteService->devServerRunning()) {
            $viteService->register('@vite/client', false);
        }
    }

    public static function registerSourceConnectAssets(): void
    {
        self::registerSourceConnectAsset('src/video-picker-source-connect.js');
    }
}
