<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\models\Settings;
use verbb\videopicker\records\Video as VideoRecord;
use verbb\videopicker\utilities\VideosUtility;

use Craft;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\web\Controller;

use yii\web\Response;
use yii\web\ForbiddenHttpException;

class PluginController extends Controller
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        if (in_array($action->id, ['clear-video-cache', 'clear-source-cache'], true)) {
            $this->requireCpRequest();
            $this->requirePostRequest();

            // Use the same permission and disabled-utility policy as Craft's utility screen.
            if (!Craft::$app->getUtilities()->checkAuthorization(VideosUtility::class)) {
                throw new ForbiddenHttpException('User is not authorized to perform this action.');
            }
        }

        return true;
    }

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

        $source = VideoPicker::$plugin->getSources()->getSourceById((int)$sourceId);
        $source?->clearExplorerCache();

        Craft::$app->getSession()->setNotice(Craft::t('video-picker', 'Source cache cleared.'));

        return $this->redirectToPostedUrl();
    }
}
