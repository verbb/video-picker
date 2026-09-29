<?php

declare(strict_types=1);

use craft\elements\GlobalSet;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\controllers\VideosController;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\sources\Wistia;

class ExplorerFailureWistia extends Wistia
{
    public static array $calls = [];
    public static ?string $failingHandle = null;

    public function isConnected(): bool { return true; }

    protected function fetchExplorerSections(): array
    {
        self::$calls[] = $this->handle;
        if ($this->handle === self::$failingHandle) {
            throw new RuntimeException('Provider collection discovery failed.');
        }

        return [['name' => 'Library', 'collections' => [['name' => 'Videos', 'method' => 'medias']]]];
    }
}

it('lists allowed sources independently of provider failures and hydrates only the chosen source', function() {
    AdminUser::login();
    $suffix = bin2hex(random_bytes(4));
    $field = new VideoPickerField(['name' => 'Provider recovery', 'handle' => 'recovery' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    $layout = new FieldLayout(['type' => GlobalSet::class]);
    $layout->setTabs([new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'elements' => [new CustomField($field)]])]);
    expect(Craft::$app->getFields()->saveLayout($layout))->toBeTrue();
    $set = new GlobalSet(['name' => 'Provider recovery', 'handle' => 'recovery' . $suffix, 'fieldLayoutId' => $layout->id]);
    $set->setFieldLayout($layout);
    $sources = VideoPicker::$plugin->getSources();
    $first = new ExplorerFailureWistia(['name' => 'Outage ' . $suffix, 'handle' => 'outage' . $suffix, 'enabled' => true, 'accessToken' => 'fixture', 'fields' => [$field->uid]]);
    $second = new ExplorerFailureWistia(['name' => 'Healthy ' . $suffix, 'handle' => 'healthy' . $suffix, 'enabled' => true, 'accessToken' => 'fixture', 'fields' => [$field->uid]]);
    ExplorerFailureWistia::$failingHandle = $first->handle;
    ExplorerFailureWistia::$calls = [];

    try {
        expect($sources->saveSource($first))->toBeTrue()
            ->and($sources->saveSource($second))->toBeTrue()
            ->and(Craft::$app->getGlobals()->saveSet($set))->toBeTrue();
        CpRequestContext::activate('actions/video-picker/videos/get-sources');
        Craft::$app->getRequest()->setIsConsoleRequest(true);
        $context = ['fieldId' => $field->id, 'elementId' => $set->id, 'siteId' => $set->siteId];
        $controller = new VideosController('videos', VideoPicker::$plugin);

        foreach ([[], ['refresh' => true]] as $options) {
            Craft::$app->getRequest()->setBodyParams($context + $options);
            $data = $controller->actionGetSources()->data;
            expect(array_column($data, 'handle'))->toContain($first->handle, $second->handle)
                ->and(ExplorerFailureWistia::$calls)->toBe([]);
        }

        Craft::$app->getRequest()->setBodyParams($context + ['hydrate' => $second->handle]);
        $data = $controller->actionGetSources()->data;
        $healthy = array_values(array_filter($data, fn(array $source) => $source['handle'] === $second->handle))[0];
        expect($healthy['sections'][0]['collections'][0]['method'])->toBe('medias')
            ->and(ExplorerFailureWistia::$calls)->toBe([$second->handle]);

        Craft::$app->getRequest()->setBodyParams($context + ['hydrate' => $first->handle, 'refresh' => true]);
        expect(fn() => $controller->actionGetSources())->toThrow(RuntimeException::class, 'Provider collection discovery failed.');
        ExplorerFailureWistia::$failingHandle = null;
        expect($controller->actionGetSources()->data)->not->toBeEmpty();
    } finally {
        Craft::$app->getRequest()->setIsCpRequest(false);
        ExplorerFailureWistia::$failingHandle = null;
        if ($set->id) {
            Craft::$app->getGlobals()->deleteSet($set);
        }
        foreach ([$first, $second] as $source) {
            if ($source->id) {
                $sources->deleteSource($source);
            }
        }
        Craft::$app->getFields()->deleteField($field);
    }
});
