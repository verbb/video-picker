<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\SourceInterface;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class SourcesController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionIndex(): Response
    {
        $sources = VideoPicker::$plugin->getSources()->getAllSources();

        return $this->renderTemplate('video-picker/sources', [
            'sources' => $sources,
        ]);
    }

    public function actionEdit(?string $handle = null, SourceInterface $source = null): Response
    {
        $sourcesService = VideoPicker::$plugin->getSources();

        if ($source === null) {
            if ($handle !== null) {
                $source = $sourcesService->getSourceByHandle($handle);

                if ($source === null) {
                    throw new NotFoundHttpException('Source not found');
                }
            }
        }

        $allSourceTypes = $sourcesService->getAllSourceTypes();

        $sourceInstances = [];
        $sourceOptions = [];

        foreach ($allSourceTypes as $sourceType) {
            /** @var SourceInterface $sourceInstance */
            $sourceInstance = Craft::createObject($sourceType);

            if ($source === null) {
                $source = $sourceInstance;
            }

            $sourceInstances[$sourceType] = $sourceInstance;

            $sourceOptions[] = [
                'value' => $sourceType,
                'label' => $sourceInstance::displayName(),
            ];
        }

        // Sort them by name
        ArrayHelper::multisort($sourceOptions, 'label');

        if ($handle && $sourcesService->getSourceByHandle($handle)) {
            $title = trim($source->name) ?: Craft::t('video-picker', 'Edit Source');
        } else {
            $title = Craft::t('video-picker', 'Create a new source');
        }

        return $this->renderTemplate('video-picker/sources/_edit', [
            'title' => $title,
            'source' => $source,
            'sourceOptions' => $sourceOptions,
            'sourceInstances' => $sourceInstances,
            'sourceTypes' => $allSourceTypes,
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $sourcesService = VideoPicker::$plugin->getSources();
        $sourceId = $this->request->getParam('sourceId') ?: null;
        $type = $this->request->getParam('type');

        if ($sourceId) {
            $oldSource = $sourcesService->getSourceById($sourceId);
            
            if (!$oldSource) {
                throw new BadRequestHttpException("Invalid source ID: $sourceId");
            }
        }

        $source = $sourcesService->createSource([
            'id' => $sourceId,
            'type' => $type,
            'name' => $this->request->getParam('name'),
            'handle' => $this->request->getParam('handle'),
            'enabled' => (bool)$this->request->getParam('enabled'),
            'settings' => $this->request->getParam("types.$type"),
        ]);

        if (!$sourcesService->saveSource($source)) {
            return $this->asModelFailure($source, Craft::t('video-picker', 'Couldn’t save source.'), 'source');
        }

        return $this->asModelSuccess($source, Craft::t('video-picker', 'Source saved.'), 'source');
    }

    public function actionReorder(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $sourceIds = Json::decode($this->request->getRequiredBodyParam('ids'));
        VideoPicker::$plugin->getSources()->reorderSources($sourceIds);

        return $this->asSuccess();
    }

    public function actionDelete(): Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        $sourceId = $this->request->getRequiredBodyParam('id');

        VideoPicker::$plugin->getSources()->deleteSourceById($sourceId);

        return $this->asSuccess();
    }
}
