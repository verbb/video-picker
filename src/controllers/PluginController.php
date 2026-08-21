<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\models\Settings;
use verbb\videopicker\records\Video as VideoRecord;

use Craft;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\web\Controller;

use yii\web\Response;

class PluginController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionSettings(): Response
    {
        /* @var Settings $settings */
        $settings = VideoPicker::$plugin->getSettings();

        return $this->renderTemplate('video-picker/settings', [
            'settings' => $settings,
        ]);
    }

    public function actionSettingsCache(): Response
    {
        /* @var Settings $settings */
        $settings = VideoPicker::$plugin->getSettings();

        return $this->renderTemplate('video-picker/settings/cache', [
            'settings' => $settings,
        ]);
    }

    public function actionSettingsEmbed(): Response
    {
        /* @var Settings $settings */
        $settings = VideoPicker::$plugin->getSettings();

        return $this->renderTemplate('video-picker/settings/embed', [
            'settings' => $settings,
        ]);
    }

    /**
     * Save plugin settings from a partial CP form without wiping other keys.
     * Craft’s `plugins/save-plugin-settings` only persists posted keys into project config.
     */
    public function actionSaveSettings(): ?Response
    {
        $this->requirePostRequest();
        $this->requireAdmin();

        /* @var Settings $settings */
        $settings = VideoPicker::$plugin->getSettings();
        $settings->setAttributes($this->request->getBodyParam('settings') ?? [], false);

        $screens = [
            'cache' => 'video-picker/settings/cache',
            'embed' => 'video-picker/settings/embed',
            'general' => 'video-picker/settings',
        ];
        $screen = $this->request->getBodyParam('settingsScreen');
        $template = $screens[$screen] ?? $screens['general'];

        if (!$settings->validate()) {
            Craft::$app->getSession()->setError(Craft::t('video-picker', 'Couldn’t save settings.'));

            return $this->renderTemplate($template, [
                'settings' => $settings,
            ]);
        }

        if (!Craft::$app->getPlugins()->savePluginSettings(VideoPicker::$plugin, $settings->toArray())) {
            Craft::$app->getSession()->setError(Craft::t('video-picker', 'Couldn’t save settings.'));

            return $this->renderTemplate($template, [
                'settings' => $settings,
            ]);
        }

        Craft::$app->getSession()->setNotice(Craft::t('video-picker', 'Settings saved.'));

        return $this->redirectToPostedUrl();
    }

    public function actionClearVideoCache(): ?Response
    {
        $videoUrl = $this->request->getRequiredBodyParam('videoUrl');

        if ($videoUrl) {
            Db::delete('{{%video_picker_videos}}', ['videoUrl' => $videoUrl]);

            Craft::$app->getSession()->setNotice(Craft::t('video-picker', 'Video cache cleared.'));
        }

        return $this->redirectToPostedUrl();
    }

    public function actionClearSourceCache(): Response
    {
        $this->requirePostRequest();
        $sourceId = $this->request->getRequiredBodyParam('sourceId');

        Db::update('{{%video_picker_sources}}', ['cache' => null], ['id' => $sourceId]);

        $source = VideoPicker::$plugin->getSources()->getSourceById((int)$sourceId);
        $source?->clearLocalCache();

        Craft::$app->getSession()->setNotice(Craft::t('video-picker', 'Source cache cleared.'));

        return $this->redirectToPostedUrl();
    }
}
