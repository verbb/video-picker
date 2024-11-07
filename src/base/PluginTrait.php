<?php
namespace verbb\videopicker\base;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\services\Service;
use verbb\videopicker\services\Sources;
use verbb\videopicker\services\Videos;
use verbb\videopicker\web\assets\field\VideoPickerAsset;

use Craft;

use yii\log\Logger;

use verbb\auth\Auth;
use verbb\base\BaseHelper;

use nystudio107\pluginvite\services\VitePluginService;

trait PluginTrait
{
    // Static Properties
    // =========================================================================

    public static VideoPicker $plugin;


    // Public Methods
    // =========================================================================

    public static function log(string $message, array $attributes = []): void
    {
        if ($attributes) {
            $message = Craft::t('video-picker', $message, $attributes);
        }

        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'video-picker');
    }

    public static function error(string $message, array $attributes = []): void
    {
        if ($attributes) {
            $message = Craft::t('video-picker', $message, $attributes);
        }

        Craft::getLogger()->log($message, Logger::LEVEL_ERROR, 'video-picker');
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


    // Private Methods
    // =========================================================================

    private function _setPluginComponents(): void
    {
        $this->setComponents([
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
        ]);

        Auth::registerModule();
        BaseHelper::registerModule();
    }

    private function _setLogging(): void
    {
        BaseHelper::setFileLogging('video-picker');
    }

}