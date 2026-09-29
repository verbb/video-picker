<?php
namespace verbb\videopicker\base;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\services\Service;
use verbb\videopicker\services\Sources;
use verbb\videopicker\services\Videos;
use verbb\videopicker\web\assets\field\VideoPickerAsset as FieldVideoPickerAsset;

use Craft;
use craft\helpers\App;

use verbb\base\LogTrait;
use verbb\base\helpers\Plugin;

use verbb\auth\Auth;

use nystudio107\pluginvite\services\VitePluginService;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static ?VideoPicker $plugin = null;


    // Traits
    // =========================================================================

    use LogTrait;


    // Static Methods
    // =========================================================================

    public static function config(): array
    {
        Plugin::bootstrapPlugin('video-picker');

        return [
            'components' => [
                'service' => Service::class,
                'sources' => Sources::class,
                'videos' => Videos::class,
                'vite' => [
                    'class' => VitePluginService::class,
                    'assetClass' => FieldVideoPickerAsset::class,
                    'useDevServer' => App::parseBooleanEnv('$VIDEO_PICKER_USE_VITE_DEV_SERVER') ?? false,
                    'devServerPublic' => 'http://localhost:4035/',
                    'errorEntry' => 'field/src/js/video-picker.ts',
                    'cacheKeySuffix' => '',
                    'devServerInternal' => 'http://localhost:4035/',
                    'checkDevServer' => true,
                    'includeReactRefreshShim' => false,
                ],
                'cpAssets' => [
                    'class' => VitePluginService::class,
                    'assetClass' => \verbb\videopicker\web\assets\cp\CpAsset::class,
                    'useDevServer' => App::parseBooleanEnv('$VIDEO_PICKER_CP_USE_VITE_DEV_SERVER') ?? false,
                    'devServerPublic' => 'http://localhost:4036/',
                    'errorEntry' => 'src/video-picker-cp.js',
                    'cacheKeySuffix' => '-cp',
                    'devServerInternal' => 'http://localhost:4036/',
                    'checkDevServer' => true,
                    'includeReactRefreshShim' => false,
                ],
                'sourceConnectAssets' => [
                    'class' => VitePluginService::class,
                    'assetClass' => \verbb\videopicker\web\assets\sourceconnect\SourceConnectAsset::class,
                    'useDevServer' => App::parseBooleanEnv('$VIDEO_PICKER_SOURCE_CONNECT_USE_VITE_DEV_SERVER') ?? false,
                    'devServerPublic' => 'http://localhost:4037/',
                    'errorEntry' => 'src/video-picker-source-connect.js',
                    'cacheKeySuffix' => '-source-connect',
                    'devServerInternal' => 'http://localhost:4037/',
                    'checkDevServer' => true,
                    'includeReactRefreshShim' => false,
                ],
            ],
        ];
    }


    // Public Methods
    // =========================================================================

    public function getService(): Service
    {
        return $this->get('service');
    }

    public function getSources(): Sources
    {
        return $this->get('sources');
    }

    public function getVideos(): Videos
    {
        return $this->get('videos');
    }

    public function getVite(): VitePluginService
    {
        return $this->get('vite');
    }

    public function getCpAssets(): VitePluginService
    {
        return $this->get('cpAssets');
    }

    public function getSourceConnectAssets(): VitePluginService
    {
        return $this->get('sourceConnectAssets');
    }
}
