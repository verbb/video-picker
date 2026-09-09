<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;

use Craft;
use craft\base\ElementInterface;
use craft\base\FieldInterface;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

class VideosController extends Controller
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        // Explorer / resolve are CP field chrome only — never front-end action routes.
        $this->requireCpRequest();
        $this->requirePostRequest();
        // Separate from Sources (configure providers): who may browse/resolve via fields.
        $this->requirePermission('videoPicker-explore');

        return true;
    }

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

        if ($method === 'search' && !$field->allowSearch) {
            throw new BadRequestHttpException(Craft::t('video-picker', 'Search is disabled for this field.'));
        }

        // Reject browse requests for sources the field does not allow.
        $source = VideoPicker::$plugin->getSources()->getSourceByHandleForField($sourceHandle, $field);

        if (!$source) {
            throw new BadRequestHttpException(Craft::t('video-picker', 'Unable to find source “{source}”.', [
                'source' => $sourceHandle,
            ]));
        }

        $videosResponse = $source->getVideos($method, $options, $field->resolveVideosPerPage());

        $videos = [];

        foreach ($field->applyExplorerVideoPolicy($videosResponse['videos'] ?? []) as $video) {
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

        if ($reason = $field->selectionPolicyError($video)) {
            return $this->asErrorJson($reason);
        }

        return $this->asJson($video->getVideoData());
    }


    // Private Methods
    // =========================================================================

    /**
     * Field AJAX must scope to a real Video Picker field (Available Fields) on an
     * element the caller can view — never browse by fieldId alone.
     */
    private function _getVideoPickerField(): VideoPickerField
    {
        $fieldId = $this->request->getRequiredParam('fieldId');
        $field = Craft::$app->getFields()->getFieldById((int)$fieldId);

        if (!$field instanceof VideoPickerField) {
            throw new BadRequestHttpException(Craft::t('video-picker', 'Invalid Video Picker field.'));
        }

        $elementId = $this->request->getRequiredParam('elementId');
        $siteId = (int)($this->request->getParam('siteId') ?: Craft::$app->getSites()->getCurrentSite()->id);
        $element = Craft::$app->getElements()->getElementById((int)$elementId, null, $siteId);

        if (!$element instanceof ElementInterface) {
            throw new ForbiddenHttpException(Craft::t('video-picker', 'Unable to find the element for this field.'));
        }

        if (!Craft::$app->getElements()->canView($element)) {
            throw new ForbiddenHttpException(Craft::t('video-picker', 'You are not permitted to browse videos for this element.'));
        }

        if (!$this->_elementLayoutContainsField($element, $field)) {
            throw new ForbiddenHttpException(Craft::t('video-picker', 'This field is not part of the element being edited.'));
        }

        return $field;
    }

    /**
     * Whether `$field` appears on `$element`’s field layout.
     * Callers should pass the element that owns the input (e.g. Matrix block, not only the entry).
     */
    private function _elementLayoutContainsField(ElementInterface $element, FieldInterface $field): bool
    {
        $layout = $element->getFieldLayout();

        if (!$layout) {
            return false;
        }

        foreach ($layout->getCustomFields() as $layoutField) {
            if ((int)$layoutField->id === (int)$field->id) {
                return true;
            }
        }

        return false;
    }
}
