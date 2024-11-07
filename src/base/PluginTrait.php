<?php
namespace verbb\videopicker\base;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\services\Service;
use verbb\videopicker\services\Sources;
use verbb\videopicker\services\Videos;
use verbb\videopicker\web\assets\field\VideoPickerAsset;

use Craft;

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
                    'assetClass' => VideoPickerAsset::class,
                    'useDevServer' => true,
                    'devServerPublic' => 'http://localhost:4035/',
                    'errorEntry' => 'js/main.js',
                    'cacheKeySuffix' => '',
                    'devServerInternal' => 'http://localhost:4035/',
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
}
