<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\helpers\Videos;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\web\Controller;

use Throwable;

use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class VideosController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionGetSources(): Response
    {
        $refresh = (bool)$this->request->getParam('refresh');
        $field = $this->_getVideoPickerField();
        $sources = VideoPicker::$plugin->getSources()->getSourcesForField($field);

        $data = [];

        foreach ($sources as $source) {
            $data[] = $source->getExplorerData($refresh);
        }

        return $this->asJson($data);
    }

    public function actionGetVideos(): Response
    {
        $this->requireAcceptsJson();

        $sourceHandle = $this->request->getRequiredParam('source');
        $method = $this->request->getRequiredParam('method');
        $options = $this->request->getParam('options') ?? [];
        $field = $this->_getVideoPickerField();

        // Reject browse requests for sources the field does not allow.
        $source = VideoPicker::$plugin->getSources()->getSourceByHandleForField($sourceHandle, $field);

        if (!$source) {
            throw new BadRequestHttpException(Craft::t('video-picker', 'Unable to find source “{source}”.', [
                'source' => $sourceHandle,
            ]));
        }

        $videosResponse = $source->getVideos($method, $options);

        $videos = [];

        foreach (($videosResponse['videos'] ?? []) as $video) {
            $videos[] = $video->getVideoData();
        }

        return $this->asJson([
            'videos' => $videos,
            'nextPage' => $videosResponse['nextPage'] ?? null,
        ]);
    }

    public function actionGetVideo(): Response
    {
        $this->requireAcceptsJson();

        $url = $this->request->getRequiredParam('url');
        $refresh = (bool)$this->request->getParam('refresh');
        $field = $this->_getVideoPickerField();
        $video = VideoPicker::getInstance()->getVideos()->getVideoByUrl($url, $refresh, $field);

        if (!$video) {
            return $this->asErrorJson(Craft::t('video-picker', 'Unable to find the video.'));
        }

        return $this->asJson($video->getVideoData());
    }

    // Private Methods
    // =========================================================================

    private function _getVideoPickerField(): ?VideoPickerField
    {
        $fieldId = $this->request->getParam('fieldId');

        if (!$fieldId) {
            return null;
        }

        $field = Craft::$app->getFields()->getFieldById((int)$fieldId);

        return $field instanceof VideoPickerField ? $field : null;
    }
}
