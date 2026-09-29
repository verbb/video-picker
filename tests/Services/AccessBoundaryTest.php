<?php

declare(strict_types=1);

use craft\elements\GlobalSet;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\helpers\StringHelper;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use Tests\Support\NonAdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\controllers\AuthController;
use verbb\videopicker\controllers\SourcesController;
use verbb\videopicker\controllers\VideosController;
use verbb\videopicker\fields\VideoPickerField;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\MethodNotAllowedHttpException;

describe('Video Picker plugin boot', function() {
    it('installs and exposes the plugin instance', function() {
        expect(VideoPicker::$plugin)->not->toBeNull();
        expect(Craft::$app->plugins->isPluginEnabled('video-picker'))->toBeTrue();
    });
});

describe('AuthController anonymous surface', function() {
    it('only allows the OAuth callback anonymously', function() {
        $controller = (new ReflectionClass(AuthController::class))->newInstanceWithoutConstructor();
        $prop = new ReflectionProperty(AuthController::class, 'allowAnonymous');
        $prop->setAccessible(true);

        expect($prop->getValue($controller))->toBe(['callback']);
    });

    it('requires POST for an authorized management request', function(string $action) {
        AdminUser::login();
        CpRequestContext::activate("actions/video-picker/auth/{$action}", 'GET', true);

        $controller = new AuthController('auth', VideoPicker::$plugin);

        expect(fn() => $controller->runAction($action))
            ->toThrow(MethodNotAllowedHttpException::class);
    })->with(['connect', 'disconnect']);

    it('requires source-management permission', function(string $action) {
        NonAdminUser::login();
        CpRequestContext::activate("actions/video-picker/auth/{$action}", 'POST', true);

        $controller = new AuthController('auth', VideoPicker::$plugin);
        $controller->enableCsrfValidation = false;

        expect(fn() => $controller->runAction($action))
            ->toThrow(ForbiddenHttpException::class);
    })->with(['connect', 'disconnect']);

    it('requires the dedicated credential permission', function(string $action) {
        NonAdminUser::loginWithPermissions(['videoPicker-sources']);
        CpRequestContext::activate("actions/video-picker/auth/{$action}", 'POST', true);

        $controller = new AuthController('auth', VideoPicker::$plugin);
        $controller->enableCsrfValidation = false;

        expect(fn() => $controller->runAction($action))
            ->toThrow(ForbiddenHttpException::class);
    })->with(['connect', 'disconnect']);

    it('allows credential managers to reach request validation', function(string $action) {
        NonAdminUser::loginWithPermissions(['videoPicker-sources', VideoPicker::MANAGE_SOURCE_CREDENTIALS_PERMISSION]);
        CpRequestContext::activate("actions/video-picker/auth/{$action}", 'POST', true);

        $controller = new AuthController('auth', VideoPicker::$plugin);
        $controller->enableCsrfValidation = false;

        expect(fn() => $controller->runAction($action))
            ->toThrow(BadRequestHttpException::class);
    })->with(['connect', 'disconnect']);

    it('requires the credential permission to check a saved connection', function() {
        NonAdminUser::loginWithPermissions(['videoPicker-sources']);
        CpRequestContext::activate('actions/video-picker/sources/check-connection', 'POST', true);

        $controller = new SourcesController('sources', VideoPicker::$plugin);
        $controller->enableCsrfValidation = false;

        expect(fn() => $controller->runAction('check-connection'))
            ->toThrow(ForbiddenHttpException::class);
    });
});

describe('VideosController access boundary', function() {
    it('rejects non-CP requests before the action runs', function() {
        AdminUser::login();
        CpRequestContext::activate('actions/video-picker/videos/get-video', 'POST', false);

        $controller = new VideosController('videos', VideoPicker::$plugin);
        $controller->enableCsrfValidation = false;
        $action = $controller->createAction('get-video');

        expect(fn() => $controller->beforeAction($action))
            ->toThrow(BadRequestHttpException::class);
    });

    it('rejects non-POST CP requests before the action runs', function() {
        AdminUser::login();
        CpRequestContext::activate('actions/video-picker/videos/get-video', 'GET', true);

        $controller = new VideosController('videos', VideoPicker::$plugin);
        $controller->enableCsrfValidation = false;
        $action = $controller->createAction('get-video');

        expect(fn() => $controller->beforeAction($action))
            ->toThrow(MethodNotAllowedHttpException::class);
    });

    it('requires videoPicker-explore', function() {
        NonAdminUser::login();
        CpRequestContext::activate('actions/video-picker/videos/get-video', 'POST', true);

        $controller = new VideosController('videos', VideoPicker::$plugin);
        $controller->enableCsrfValidation = false;
        $action = $controller->createAction('get-video');

        expect(fn() => $controller->beforeAction($action))
            ->toThrow(ForbiddenHttpException::class);
    });

    it('requires elementId when resolving the field context', function() {
        AdminUser::login();
        CpRequestContext::activate('actions/video-picker/videos/get-video', 'POST', true);

        /** @var \craft\web\Request $request */
        $request = Craft::$app->getRequest();
        $field = new VideoPickerField([
            'name' => 'Video access fixture',
            'handle' => 'videoAccessFixture',
        ]);
        expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
        $request->setBodyParams(['fieldId' => $field->id]);

        $controller = new VideosController('videos', VideoPicker::$plugin);
        $controller->enableCsrfValidation = false;

        $method = new ReflectionMethod(VideosController::class, '_getVideoPickerField');
        $method->setAccessible(true);

        try {
            $method->invoke($controller);
            test()->fail('Expected a missing elementId exception.');
        } catch (BadRequestHttpException $e) {
            expect($request->getBodyParam('elementId'))->toBeNull()
                ->and($e->getMessage())->toBe('Request missing required param');
        } finally {
            Craft::$app->getFields()->deleteField($field);
        }
    });

    it('returns the exact Video Picker field from the viewed element layout', function() {
        AdminUser::login();

        if (Craft::$app->getRequest() instanceof \craft\web\Request) {
            Craft::$app->getRequest()->setIsCpRequest(false);
        }

        $suffix = bin2hex(random_bytes(4));
        $field = new VideoPickerField([
            'name' => 'Video layout fixture',
            'handle' => 'videoLayout' . $suffix,
        ]);
        expect(Craft::$app->getFields()->saveField($field))->toBeTrue();

        $layout = new FieldLayout([
            'uid' => StringHelper::UUID(),
            'type' => GlobalSet::class,
        ]);
        $layout->setTabs([new FieldLayoutTab([
            'layout' => $layout,
            'name' => 'Content',
            'elements' => [new CustomField($field)],
        ])]);
        expect(Craft::$app->getFields()->saveLayout($layout))->toBeTrue();
        $set = new GlobalSet([
            'name' => 'Video layout fixture',
            'handle' => 'videoLayout' . $suffix,
            'fieldLayoutId' => $layout->id,
        ]);
        $set->setFieldLayout($layout);

        try {
            expect(Craft::$app->getGlobals()->saveSet($set))->toBeTrue();
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
            CpRequestContext::activate('actions/video-picker/videos/get-video', 'POST', true);
            Craft::$app->getRequest()->setIsConsoleRequest(true);
            Craft::$app->getRequest()->setBodyParams([
                'fieldId' => $field->id,
                'elementId' => $set->id,
                'siteId' => $siteId,
            ]);
            $controller = new VideosController('videos', VideoPicker::$plugin);
            $controller->enableCsrfValidation = false;
            $method = new ReflectionMethod(VideosController::class, '_getVideoPickerField');
            $method->setAccessible(true);

            $resolved = $method->invoke($controller);
            expect($resolved)->toBeInstanceOf(VideoPickerField::class)
                ->and($resolved->id)->toBe($field->id);
        } finally {
            Craft::$app->getRequest()->setIsCpRequest(false);

            if ($set->id) {
                Craft::$app->getGlobals()->deleteSet($set);
            }
            Craft::$app->getFields()->deleteField($field);
        }
    });

    it('registers the Explore videos permission', function() {
        $permissions = Craft::$app->getUserPermissions()->getAllPermissions();
        $labels = [];
        $collectLabels = function(array $permissions) use (&$collectLabels, &$labels): void {
            foreach ($permissions as $handle => $info) {
                $labels[$handle] = $info['label'] ?? $handle;

                if (isset($info['nested'])) {
                    $collectLabels($info['nested']);
                }
            }
        };

        foreach ($permissions as $group) {
            $collectLabels($group['permissions'] ?? []);
        }

        expect($labels)->toHaveKey('videoPicker-explore');
        expect($labels)->toHaveKey('videoPicker-sources');
        expect($labels)->toHaveKey(VideoPicker::MANAGE_SOURCE_CREDENTIALS_PERMISSION);
    });
});
