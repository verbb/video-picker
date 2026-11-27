<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\helpers\Plugin as PluginHelper;
use verbb\videopicker\helpers\SourceSecurity;
use verbb\videopicker\base\CredentialSourceInterface;
use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\fields\VideoPickerField;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\web\Controller;

use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

use Throwable;

use verbb\base\helpers\EnvironmentSettings;
use verbb\base\models\EnvironmentSettingsViolation;

class SourcesController extends Controller
{
    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        if (!parent::beforeAction($action)) {
            return false;
        }

        $this->requirePermission('videoPicker-sources');

        return true;
    }

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

        if ($source->id && $source->supportsConnection()) {
            PluginHelper::registerSourceConnectAssets();
        }

        return $this->renderTemplate('video-picker/sources/_edit', [
            'title' => $title,
            'source' => $source,
            'sourceOptions' => $sourceOptions,
            'sourceInstances' => $sourceInstances,
            'sourceTypes' => $allSourceTypes,
            'fieldOptions' => $this->_videoPickerFieldOptions(),
        ]);
    }

    public function actionSave(): ?Response
    {
        $this->requirePostRequest();

        $sourcesService = VideoPicker::$plugin->getSources();
        $sourceId = $this->request->getParam('sourceId') ?: null;
        $type = $this->request->getParam('type');
        $oldSource = null;
        $storedSource = null;

        if ($sourceId) {
            $oldSource = $sourcesService->getSourceById($sourceId);
            $storedSource = $sourcesService->getStoredSourceById($sourceId);

            if (!$oldSource || !$storedSource) {
                throw new BadRequestHttpException("Invalid source ID: $sourceId");
            }
        }

        // Available Fields lives on the source (not the field) so prod can grant fields without PC.
        $fields = $this->request->getBodyParam('fields', $oldSource->fields ?? '*');

        if ($fields === '') {
            // Craft posts an empty string when every checkbox, including All, is unchecked.
            $fields = [];
        }

        $source = $sourcesService->createSource([
            'id' => $sourceId,
            'type' => $type,
            'name' => $this->request->getParam('name'),
            'handle' => $this->request->getParam('handle'),
            'enabled' => (bool)$this->request->getParam('enabled'),
            'fields' => $fields,
            'settings' => $this->request->getParam("types.$type"),
        ]);

        $canManageSourceCredentials = Craft::$app->getUser()->checkPermission(VideoPicker::MANAGE_SOURCE_CREDENTIALS_PERMISSION);
        $responseSource = $canManageSourceCredentials ? $source : SourceSecurity::redactedSource($source);
        $sourceToSave = $source;

        if ($oldSource && !$canManageSourceCredentials) {
            $sourceToSave = SourceSecurity::prepareDelegatedSource($source, $oldSource);
        }

        if (!$this->_validateDelegatedSourceChange($sourceToSave, $oldSource)) {
            $responseSource->addErrors($sourceToSave->getErrors());

            return $this->asModelFailure($responseSource, Craft::t('video-picker', 'Couldn’t save source.'), 'source');
        }

        // Delegated managers can't change credentials or references at all, so only credential managers need this.
        if ($canManageSourceCredentials && ($violation = $this->_environmentSettingsViolation((string)$type, $sourceToSave, $storedSource, [$storedSource?->handle, $sourceToSave->handle]))) {
            $responseSource->addError($violation->getAttribute() ?? 'type', $this->_environmentSettingsMessage($violation));

            return $this->asModelFailure($responseSource, Craft::t('video-picker', 'Couldn’t save source.'), 'source');
        }

        $settingsForPersistence = null;

        if ($storedSource && !$canManageSourceCredentials) {
            $settingsForPersistence = SourceSecurity::settingsForPersistence($sourceToSave, $storedSource);
        }

        if (!$sourcesService->saveSource($sourceToSave, true, $settingsForPersistence)) {
            $responseSource->addErrors($sourceToSave->getErrors());

            return $this->asModelFailure($responseSource, Craft::t('video-picker', 'Couldn’t save source.'), 'source');
        }

        $responseSource->id = $sourceToSave->id;

        return $this->asModelSuccess($responseSource, Craft::t('video-picker', 'Source saved.'), 'source');
    }

    public function actionCheckConnection(): Response
    {
        $this->requirePostRequest();
        $this->requirePermission(VideoPicker::MANAGE_SOURCE_CREDENTIALS_PERMISSION);
        $this->requireAcceptsJson();

        $sourceId = (int)$this->request->getParam('sourceId');
        $type = (string)$this->request->getParam('type');

        if (!$sourceId) {
            return $this->asFailure(Craft::t('video-picker', 'No source exists with the ID “{id}”.', ['id' => $sourceId]));
        }

        $source = VideoPicker::$plugin->getSources()->getSourceById($sourceId);

        if (!$source) {
            return $this->asFailure(Craft::t('video-picker', 'No source exists with the ID “{id}”.', ['id' => $sourceId]));
        }

        // Merge posted provider settings so Refresh reflects unsaved form values (Formie pattern).
        $settings = $this->request->getParam("types.$type", []);

        if ($type && is_array($settings)) {
            $source = VideoPicker::$plugin->getSources()->createSource([
                'id' => $source->id,
                'name' => $this->request->getParam('name', $source->name),
                'handle' => $this->request->getParam('handle', $source->handle),
                'enabled' => (bool)$this->request->getParam('enabled', $source->enabled),
                'fields' => $source->fields,
                'type' => $type,
                'settings' => array_merge($source->getSettings(), $settings),
                'cache' => $source->cache,
                'uid' => $source->uid,
            ]);

            $storedSource = VideoPicker::$plugin->getSources()->getStoredSourceById($sourceId);

            // The check uses this unsaved source, so check what was posted before it connects. `createSource()` only
            // applied the posted handle's config overrides, so only those keys replace posted values here.
            if ($violation = $this->_environmentSettingsViolation($type, $source, $storedSource, [$source->handle])) {
                return $this->asFailure($this->_environmentSettingsMessage($violation));
            }
        }

        if (!$source::supportsConnection() || $source::supportsOAuthConnection()) {
            return $this->asFailure(Craft::t('video-picker', 'This source does not support credential refresh.'));
        }

        if (!$source->isConfigured()) {
            return $this->asFailure(Craft::t('video-picker', 'Provider credentials are not configured.'));
        }

        try {
            $success = $source->checkConnection(false);

            if (!$success) {
                return $this->asFailure(Craft::t('video-picker', 'Unable to connect to the provider.'));
            }

            return $this->asJson([
                'success' => true,
            ]);
        } catch (Throwable $e) {
            throw $e;
        }
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

        $sourceId = $this->request->getBodyParam('id') ?? $this->request->getRequiredBodyParam('sourceId');

        VideoPicker::$plugin->getSources()->deleteSourceById($sourceId);

        return $this->asSuccess();
    }

    // Private Methods
    // =========================================================================

    /**
     * @return array{label: string, value: string}[]
     */
    private function _videoPickerFieldOptions(): array
    {
        $options = [];

        foreach (Craft::$app->getFields()->getAllFields() as $field) {
            if (!$field instanceof VideoPickerField || !$field->uid) {
                continue;
            }

            $options[] = [
                'label' => $field->name . ' (' . $field->handle . ')',
                'value' => $field->uid,
            ];
        }

        ArrayHelper::multisort($options, 'label');

        return $options;
    }

    private function _validateDelegatedSourceChange(SourceInterface $source, ?SourceInterface $original): bool
    {
        if (Craft::$app->getUser()->checkPermission(VideoPicker::MANAGE_SOURCE_CREDENTIALS_PERMISSION)) {
            return true;
        }

        return SourceSecurity::validateDelegatedChange($source, $original);
    }

    /**
     * Credential managers who aren't admins can type credentials, but only admins can point a setting at an
     * environment variable, because resolved values reach embed URLs, CP labels and OAuth authorize URLs. Posted
     * settings are compared with the database values, not config overrides, since overrides are never saved from
     * the form.
     */
    private function _environmentSettingsViolation(string $type, SourceInterface $source, ?SourceInterface $storedSource, array $overrideHandles): ?EnvironmentSettingsViolation
    {
        $posted = $this->request->getParam("types.$type");
        $posted = is_array($posted) ? $posted : [];

        // Config overrides replace posted values for their keys, so a reference the form shows from config isn't one
        // the user added.
        foreach (array_unique(array_filter($overrideHandles)) as $handle) {
            $posted = array_diff_key($posted, VideoPicker::$plugin->getSources()->getSourceOverrides($handle));
        }

        $stored = $storedSource?->getSettings() ?? [];

        $credentials = $storedSource instanceof CredentialSourceInterface
            ? $storedSource->getCredentialAttributes()
            : array_keys(array_filter($stored, fn($value) => EnvironmentSettings::containsReference($value)));

        // A new provider only carries over credentials it shares a setting name with.
        $credentials = array_values(array_intersect($credentials, array_keys($source->getSettings())));
        $providerChanged = $storedSource !== null && get_class($storedSource) !== get_class($source);

        return EnvironmentSettings::violation($posted, $stored, $credentials, false, $providerChanged);
    }

    private function _environmentSettingsMessage(EnvironmentSettingsViolation $violation): string
    {
        return $violation->reason === EnvironmentSettingsViolation::REFERENCE
            ? Craft::t('video-picker', 'Only admins can use environment variables here.')
            : Craft::t('video-picker', 'Enter the credentials again after changing where this connects.');
    }
}
