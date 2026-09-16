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
