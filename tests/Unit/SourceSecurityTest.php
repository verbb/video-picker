<?php

declare(strict_types=1);

use Tests\Support\NonAdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\controllers\SourcesController;
use verbb\videopicker\helpers\SourceSecurity;
use verbb\videopicker\sources\BunnyStream;
use verbb\videopicker\sources\CloudflareStream;
use verbb\videopicker\sources\Dailymotion;
use verbb\videopicker\sources\Mux;
use verbb\videopicker\sources\SproutVideo;
use verbb\videopicker\sources\Vimeo;
use verbb\videopicker\sources\Wistia;
use verbb\videopicker\sources\YouTube;

describe('Delegated source settings', function() {
    it('classifies credential fields for every built-in provider', function(string $sourceClass, array $expected) {
        $source = (new ReflectionClass($sourceClass))->newInstanceWithoutConstructor();

        expect($source->getCredentialAttributes())->toBe($expected);
    })->with([
        YouTube::class => [YouTube::class, ['clientId', 'clientSecret']],
        Vimeo::class => [Vimeo::class, ['clientId', 'clientSecret']],
        BunnyStream::class => [BunnyStream::class, ['libraryId', 'streamApiKey']],
        CloudflareStream::class => [CloudflareStream::class, ['accountId', 'apiToken']],
        Dailymotion::class => [Dailymotion::class, ['apiKey', 'apiSecret']],
        Mux::class => [Mux::class, ['tokenId', 'tokenSecret']],
        SproutVideo::class => [SproutVideo::class, ['apiKey']],
        Wistia::class => [Wistia::class, ['accessToken']],
    ]);

    it('rejects new environment and alias references in ordinary settings', function(string $value) {
        $source = new Dailymotion([
            'apiKey' => 'unchanged-key',
            'apiSecret' => 'unchanged-secret',
            'channelUser' => $value,
        ]);
        $original = clone $source;
        $original->channelUser = 'channel-name';

        expect(SourceSecurity::validateDelegatedChange($source, $original))->toBeFalse()
            ->and($source->getErrors('channelUser'))->not->toBeEmpty();
    })->with(['$VIDEO_CHANNEL', '${VIDEO_CHANNEL}', 'prefix/${VIDEO_CHANNEL}', '@videoChannel']);

    it('rejects OAuth credential changes for ordinary source managers', function(string $value) {
        $source = new YouTube([
            'clientId' => $value,
            'clientSecret' => 'unchanged-secret',
        ]);

        expect(SourceSecurity::validateDelegatedChange($source))->toBeFalse()
            ->and($source->getErrors('clientId'))->not->toBeEmpty();
    })->with(['literal-client-id', '$VIDEO_CLIENT_ID', '${VIDEO_CLIENT_ID}', '@videoClientId']);

    it('allows ordinary OAuth settings to change while credentials remain unchanged', function() {
        $original = new YouTube([
            'clientId' => '$VIDEO_CLIENT_ID',
            'clientSecret' => '$VIDEO_CLIENT_SECRET',
            'privacyEnhanced' => false,
        ]);
        $changed = clone $original;
        $changed->privacyEnhanced = true;

        expect(SourceSecurity::validateDelegatedChange($changed, $original))->toBeTrue();
    });

    it('preserves stored protected values hidden by config overrides', function() {
        $stored = new YouTube([
            'clientId' => '$VIDEO_CLIENT_ID',
            'clientSecret' => '$VIDEO_CLIENT_SECRET',
            'privacyEnhanced' => false,
        ]);
        $effective = new YouTube([
            'clientId' => 'config-client-id',
            'clientSecret' => 'config-client-secret',
            'privacyEnhanced' => true,
        ]);

        expect(SourceSecurity::settingsForPersistence($effective, $stored))->toMatchArray([
            'clientId' => '$VIDEO_CLIENT_ID',
            'clientSecret' => '$VIDEO_CLIENT_SECRET',
            'privacyEnhanced' => true,
        ]);
    });

    it('allows credential managers to use environment-backed OAuth credentials', function() {
        NonAdminUser::loginWithPermissions(['videoPicker-sources', VideoPicker::MANAGE_SOURCE_CREDENTIALS_PERMISSION]);
        $source = new YouTube([
            'clientId' => '$VIDEO_CLIENT_ID',
            'clientSecret' => '$VIDEO_CLIENT_SECRET',
        ]);
        $controller = (new ReflectionClass(SourcesController::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod($controller, '_validateDelegatedSourceChange');

        expect($method->invoke($controller, $source, null))->toBeTrue();
    });

    it('rejects provider type changes without the credential permission', function() {
        $original = new YouTube([
            'clientId' => '$VIDEO_CLIENT_ID',
            'clientSecret' => '$VIDEO_CLIENT_SECRET',
        ]);
        $changed = new Wistia([
            'accessToken' => '$WISTIA_ACCESS_TOKEN',
        ]);

        expect(SourceSecurity::validateDelegatedChange($changed, $original))->toBeFalse()
            ->and($changed->getErrors('type'))->not->toBeEmpty();
    });
});
