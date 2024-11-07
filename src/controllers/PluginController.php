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
        $sourceId = $this->request->getRequiredBodyParam('sourceId');

        Db::update('{{%video_picker_sources}}', ['cache' => null], ['id' => $sourceId]);

        Craft::$app->getSession()->setNotice(Craft::t('video-picker', 'Source cache cleared.'));

        return $this->redirectToPostedUrl();
    }
}
