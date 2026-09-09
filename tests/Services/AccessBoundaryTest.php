<?php

declare(strict_types=1);

use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use Tests\Support\NonAdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\controllers\AuthController;
use verbb\videopicker\controllers\VideosController;
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
        $request->setBodyParams([
            'fieldId' => 1,
        ]);

        $controller = new VideosController('videos', VideoPicker::$plugin);
        $controller->enableCsrfValidation = false;

        $method = new ReflectionMethod(VideosController::class, '_getVideoPickerField');
        $method->setAccessible(true);

        expect(fn() => $method->invoke($controller))
            ->toThrow(BadRequestHttpException::class);
    });

    it('registers the Explore videos permission', function() {
        $permissions = Craft::$app->getUserPermissions()->getAllPermissions();
        $labels = [];

        foreach ($permissions as $group) {
            foreach (($group['permissions'] ?? []) as $handle => $info) {
                $labels[$handle] = $info['label'] ?? $handle;
            }
        }

        expect($labels)->toHaveKey('videoPicker-explore');
        expect($labels)->toHaveKey('videoPicker-sources');
    });
});
