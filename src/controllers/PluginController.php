<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\models\Settings;
use verbb\videopicker\records\Video as VideoRecord;
use verbb\videopicker\utilities\VideosUtility;

use Craft;
use craft\helpers\Db;
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
