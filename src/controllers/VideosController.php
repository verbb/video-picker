<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;

use Craft;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
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

        // Optional: hydrate sections for one handle only (explorer source switch / refresh).
        // When omitted, only the first source’s sections are fetched — avoids cold-open N× provider calls.
        $hydrateHandle = $this->request->getParam('hydrate');

        $data = [];
        $hydratedFirst = false;

        foreach ($sources as $source) {
            $includeSections = false;

            if (is_string($hydrateHandle) && $hydrateHandle !== '') {
                $includeSections = $source->handle === $hydrateHandle;
            } elseif (!$hydratedFirst) {
                $includeSections = true;
                $hydratedFirst = true;
            }

            $data[] = $source->getExplorerData($refresh && $includeSections, $includeSections);
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

        // Provider failures used to return a Video with model errors as HTTP 200.
        if ($video->hasErrors()) {
            $message = $video->getFirstError('url')
                ?: Craft::t('video-picker', 'Unable to find the video.');

            return $this->asErrorJson($message);
        }

        return $this->asJson($video->getVideoData());
    }

    // Private Methods
    // =========================================================================

    /**
     * Field AJAX must always scope to a real VideoPickerField (Available Sources hard limit).
     */
    private function _getVideoPickerField(): VideoPickerField
    {
        $fieldId = $this->request->getRequiredParam('fieldId');
        $field = Craft::$app->getFields()->getFieldById((int)$fieldId);

        if (!$field instanceof VideoPickerField) {
            throw new BadRequestHttpException(Craft::t('video-picker', 'Invalid Video Picker field.'));
        }

        return $field;
    }
}
