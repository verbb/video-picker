<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\controllers\SourcesController;
use verbb\videopicker\sources\YouTube;

it('preserves no available fields when every checkbox is cleared', function() {
    AdminUser::login();
    CpRequestContext::activate('actions/video-picker/sources/save');
    $request = Craft::$app->getRequest();
    $request->getHeaders()->set('Accept', 'application/json');
    $request->setBodyParams([
        'type' => YouTube::class,
        'name' => 'No fields fixture',
        'handle' => 'noFieldsFixture',
        'enabled' => false,
        'fields' => '',
    ]);
    $controller = new SourcesController('sources', VideoPicker::$plugin);

    try {
        $controller->actionSave();
        $source = VideoPicker::$plugin->getSources()->getSourceByHandle('noFieldsFixture');
        expect($source)->not->toBeNull()
            ->and($source->fields)->toBe([]);
    } finally {
        if ($source = VideoPicker::$plugin->getSources()->getSourceByHandle('noFieldsFixture')) {
            VideoPicker::$plugin->getSources()->deleteSource($source);
        }
    }
});

it('deletes a source submitted by the edit form', function() {
    AdminUser::login();
    $source = new YouTube(['name' => 'Delete form fixture', 'handle' => 'deleteFormFixture', 'enabled' => false]);
    expect(VideoPicker::$plugin->getSources()->saveSource($source))->toBeTrue();
    CpRequestContext::activate('actions/video-picker/sources/delete');
    $request = Craft::$app->getRequest();
    $request->setBodyParams([
        'sourceId' => $source->id,
        'redirect' => Craft::$app->getSecurity()->hashData('video-picker/sources'),
    ]);
    $controller = new SourcesController('sources', VideoPicker::$plugin);

    try {
        $controller->actionDelete();
        expect(VideoPicker::$plugin->getSources()->getSourceById($source->id))->toBeNull();
    } finally {
        VideoPicker::$plugin->getSources()->deleteSourceById($source->id);
    }
});

it('validates duplicate source names and handles without a database error', function(string $attribute) {
    AdminUser::login();
    $sources = VideoPicker::$plugin->getSources();
    $source = new YouTube(['name' => 'Unique fixture', 'handle' => 'uniqueFixture', 'enabled' => false]);
    expect($sources->saveSource($source))->toBeTrue();

    try {
        $duplicate = new YouTube(['name' => 'Other fixture', 'handle' => 'otherFixture', 'enabled' => false]);
        $duplicate->$attribute = $source->$attribute;
        expect($sources->saveSource($duplicate))->toBeFalse()
            ->and($duplicate->getErrors($attribute))->not->toBeEmpty()
            ->and($sources->saveSource($source))->toBeTrue();
    } finally {
        $sources->deleteSource($source);
    }
})->with(['name', 'handle']);
