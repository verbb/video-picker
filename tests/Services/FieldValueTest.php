<?php

declare(strict_types=1);

use craft\elements\GlobalSet;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use Tests\Support\AdminUser;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\models\Video;

it('rejects an invalid replacement without clearing the saved video', function() {
    AdminUser::login();
    $suffix = bin2hex(random_bytes(4));
    $field = new VideoPickerField(['name' => 'Video value', 'handle' => 'videoValue' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    $layout = new FieldLayout(['uid' => StringHelper::UUID(), 'type' => GlobalSet::class]);
    $layout->setTabs([new FieldLayoutTab([
        'layout' => $layout,
        'name' => 'Content',
        'elements' => [new CustomField($field)],
    ])]);
    expect(Craft::$app->getFields()->saveLayout($layout))->toBeTrue();
    $set = new GlobalSet(['name' => 'Video value', 'handle' => 'videoValue' . $suffix, 'fieldLayoutId' => $layout->id]);
    $set->setFieldLayout($layout);
    $url = 'https://www.youtube.com/watch?v=jfKfPfyJRdk';

    try {
        expect(Craft::$app->getGlobals()->saveSet($set))->toBeTrue();
        $set->setScenario(GlobalSet::SCENARIO_LIVE);
        $set->setFieldValue($field->handle, new Video(['url' => $url]));
        expect(Craft::$app->getElements()->saveElement($set))->toBeTrue();
        $savedContent = Craft::$app->getDb()->createCommand('SELECT content FROM {{%elements_sites}} WHERE elementId = :id', [':id' => $set->id])->queryScalar();

        $set->setFieldValue($field->handle, 'not a video URL');
        expect(Craft::$app->getElements()->saveElement($set))->toBeFalse()
            ->and($set->getErrors($field->handle))->not->toBeEmpty()
            ->and($set->getFieldValue($field->handle)->url)->toBe('not a video URL')
            ->and(Craft::$app->getDb()->createCommand('SELECT content FROM {{%elements_sites}} WHERE elementId = :id', [':id' => $set->id])->queryScalar())->toBe($savedContent);

        $set->setFieldValue($field->handle, '');
        expect(Craft::$app->getElements()->saveElement($set))->toBeTrue()
            ->and($set->getFieldValue($field->handle))->toBeNull();
    } finally {
        if ($set->id) {
            Craft::$app->getGlobals()->deleteSet($set);
        }
        Craft::$app->getFields()->deleteField($field);
    }
});
