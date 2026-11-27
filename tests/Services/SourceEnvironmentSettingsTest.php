<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use Tests\Support\CpRequestContext;
use Tests\Support\NonAdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\controllers\SourcesController;
use verbb\videopicker\sources\BunnyStream;
use verbb\videopicker\sources\Vimeo;
use verbb\videopicker\sources\YouTube;

const VIDEO_PICKER_REFERENCE_ERROR = 'Only admins can use environment variables here.';
const VIDEO_PICKER_REENTRY_ERROR = 'Enter the credentials again after changing where this connects.';

function videoPickerLoginCredentialManager(): void
{
    NonAdminUser::loginWithPermissions(['videoPicker-sources', VideoPicker::MANAGE_SOURCE_CREDENTIALS_PERMISSION]);
}

function videoPickerPostSourceAction(string $action, array $bodyParams): mixed
{
    CpRequestContext::activate("actions/video-picker/sources/$action");
    $request = Craft::$app->getRequest();
    $request->getHeaders()->set('Accept', 'application/json');
    $request->setBodyParams($bodyParams + ['enabled' => false, 'fields' => '*']);
    $controller = new SourcesController('sources', VideoPicker::$plugin);

    return $action === 'save' ? $controller->actionSave() : $controller->actionCheckConnection();
}

function videoPickerSourceParams(SourceInterface $source, string $type, array $settings): array
{
    return [
        'sourceId' => $source->id,
        'type' => $type,
        'name' => $source->name,
        'handle' => $source->handle,
        'types' => [$type => $settings],
    ];
}

function videoPickerSaveFixture(SourceInterface $source): SourceInterface
{
    expect(VideoPicker::$plugin->getSources()->saveSource($source, false))->toBeTrue();

    return $source;
}

function videoPickerDeleteFixture(string $handle): void
{
    $sources = VideoPicker::$plugin->getSources();

    if ($source = $sources->getSourceByHandle($handle)) {
        $sources->deleteSource($source);
    }
}

describe('Environment variables in source settings', function() {
    it('refuses a new reference from a credential manager who is not an admin', function() {
        videoPickerLoginCredentialManager();

        try {
            $response = videoPickerPostSourceAction('save', [
                'type' => BunnyStream::class,
                'name' => 'Reference refused fixture',
                'handle' => 'referenceRefusedFixture',
                'types' => [BunnyStream::class => [
                    'libraryId' => '$CRAFT_SECURITY_KEY',
                    'streamApiKey' => 'literal-stream-key',
                ]],
            ]);

            expect($response->data['errors']['libraryId'] ?? [])->toBe([VIDEO_PICKER_REFERENCE_ERROR])
                ->and(VideoPicker::$plugin->getSources()->getSourceByHandle('referenceRefusedFixture'))->toBeNull();
        } finally {
            videoPickerDeleteFixture('referenceRefusedFixture');
        }
    });

    it('lets admins save a new reference', function() {
        AdminUser::login();

        try {
            videoPickerPostSourceAction('save', [
                'type' => BunnyStream::class,
                'name' => 'Admin reference fixture',
                'handle' => 'adminReferenceFixture',
                'types' => [BunnyStream::class => [
                    'libraryId' => '$CRAFT_SECURITY_KEY',
                    'streamApiKey' => 'literal-stream-key',
                ]],
            ]);
            $source = VideoPicker::$plugin->getSources()->getSourceByHandle('adminReferenceFixture');

            expect($source)->not->toBeNull()
                ->and(VideoPicker::$plugin->getSources()->getStoredSourceById($source->id)->libraryId)->toBe('$CRAFT_SECURITY_KEY');
        } finally {
            videoPickerDeleteFixture('adminReferenceFixture');
        }
    });

    it('keeps references an admin already saved', function() {
        $source = videoPickerSaveFixture(new BunnyStream([
            'name' => 'Saved reference fixture',
            'handle' => 'savedReferenceFixture',
            'enabled' => false,
            'libraryId' => '$VIDEO_PICKER_BUNNY_LIBRARY',
            'streamApiKey' => '$VIDEO_PICKER_BUNNY_KEY',
        ]));
        videoPickerLoginCredentialManager();

        try {
            $params = videoPickerSourceParams($source, BunnyStream::class, [
                'libraryId' => '$VIDEO_PICKER_BUNNY_LIBRARY',
                'streamApiKey' => '$VIDEO_PICKER_BUNNY_KEY',
            ]);
            $params['name'] = 'Renamed saved reference fixture';
            $response = videoPickerPostSourceAction('save', $params);
            $stored = VideoPicker::$plugin->getSources()->getStoredSourceById($source->id);

            expect($response->data['errors'] ?? [])->toBe([])
                ->and($stored->name)->toBe('Renamed saved reference fixture')
                ->and($stored->libraryId)->toBe('$VIDEO_PICKER_BUNNY_LIBRARY')
                ->and($stored->streamApiKey)->toBe('$VIDEO_PICKER_BUNNY_KEY');
        } finally {
            VideoPicker::$plugin->getSources()->deleteSourceById($source->id);
        }
    });

    it('refuses a reference in place of a saved literal credential', function() {
        $source = videoPickerSaveFixture(new BunnyStream([
            'name' => 'Literal credential fixture',
            'handle' => 'literalCredentialFixture',
            'enabled' => false,
            'libraryId' => 'literal-library',
            'streamApiKey' => 'literal-stream-key',
        ]));
        videoPickerLoginCredentialManager();

        try {
            $response = videoPickerPostSourceAction('save', videoPickerSourceParams($source, BunnyStream::class, [
                'libraryId' => '$CRAFT_SECURITY_KEY',
                'streamApiKey' => 'literal-stream-key',
            ]));

            expect($response->data['errors']['libraryId'] ?? [])->toBe([VIDEO_PICKER_REFERENCE_ERROR])
                ->and(VideoPicker::$plugin->getSources()->getStoredSourceById($source->id)->libraryId)->toBe('literal-library');

            $response = videoPickerPostSourceAction('save', videoPickerSourceParams($source, BunnyStream::class, [
                'libraryId' => 'other-literal-library',
                'streamApiKey' => 'literal-stream-key',
            ]));

            expect($response->data['errors'] ?? [])->toBe([])
                ->and(VideoPicker::$plugin->getSources()->getStoredSourceById($source->id)->libraryId)->toBe('other-literal-library');
        } finally {
            VideoPicker::$plugin->getSources()->deleteSourceById($source->id);
        }
    });

    it('asks for saved reference credentials again when the provider changes', function() {
        $source = videoPickerSaveFixture(new YouTube([
            'name' => 'Provider change fixture',
            'handle' => 'providerChangeFixture',
            'enabled' => false,
            'clientId' => '$VIDEO_PICKER_CLIENT_ID',
            'clientSecret' => '$VIDEO_PICKER_CLIENT_SECRET',
        ]));
        videoPickerLoginCredentialManager();

        try {
            $response = videoPickerPostSourceAction('save', videoPickerSourceParams($source, Vimeo::class, [
                'clientId' => '$VIDEO_PICKER_CLIENT_ID',
                'clientSecret' => '$VIDEO_PICKER_CLIENT_SECRET',
            ]));

            expect($response->data['errors']['clientId'] ?? [])->toBe([VIDEO_PICKER_REENTRY_ERROR])
                ->and(VideoPicker::$plugin->getSources()->getStoredSourceById($source->id))->toBeInstanceOf(YouTube::class);

            $response = videoPickerPostSourceAction('save', videoPickerSourceParams($source, Vimeo::class, [
                'clientId' => 'literal-vimeo-client-id',
                'clientSecret' => 'literal-vimeo-client-secret',
            ]));
            $stored = VideoPicker::$plugin->getSources()->getStoredSourceById($source->id);

            expect($response->data['errors'] ?? [])->toBe([])
                ->and($stored)->toBeInstanceOf(Vimeo::class)
                ->and($stored->clientId)->toBe('literal-vimeo-client-id');
        } finally {
            VideoPicker::$plugin->getSources()->deleteSourceById($source->id);
        }
    });

    it('ignores references shown from config overrides', function() {
        $sources = VideoPicker::$plugin->getSources();
        $source = videoPickerSaveFixture(new BunnyStream([
            'name' => 'Config reference fixture',
            'handle' => 'configReferenceFixture',
            'enabled' => false,
            'libraryId' => 'stored-library',
            'streamApiKey' => 'stored-stream-key',
        ]));
        $overridesProperty = new ReflectionProperty($sources, '_overrides');
        $sourcesProperty = new ReflectionProperty($sources, '_sources');
        $originalOverrides = $overridesProperty->getValue($sources);
        $overridesProperty->setValue($sources, [
            'sources' => [
                $source->handle => ['libraryId' => '$VIDEO_PICKER_CONFIG_LIBRARY'],
            ],
        ]);
        $sourcesProperty->setValue($sources, null);
        videoPickerLoginCredentialManager();

        try {
            $response = videoPickerPostSourceAction('save', videoPickerSourceParams($source, BunnyStream::class, [
                'libraryId' => '$VIDEO_PICKER_CONFIG_LIBRARY',
                'streamApiKey' => 'stored-stream-key',
            ]));

            expect($response->data['errors'] ?? [])->toBe([])
                ->and($sources->getStoredSourceById($source->id)->libraryId)->toBe('stored-library');
        } finally {
            $overridesProperty->setValue($sources, $originalOverrides);
            $sourcesProperty->setValue($sources, null);
            $sources->deleteSourceById($source->id);
        }
    });

    it('refuses a new reference when checking an unsaved connection', function() {
        $source = videoPickerSaveFixture(new BunnyStream([
            'name' => 'Check connection fixture',
            'handle' => 'checkConnectionFixture',
            'enabled' => false,
            'libraryId' => 'literal-library',
            'streamApiKey' => 'literal-stream-key',
        ]));
        videoPickerLoginCredentialManager();

        try {
            // The empty key keeps the unfixed action from reaching Bunny, so the test never sends a request.
            $response = videoPickerPostSourceAction('check-connection', videoPickerSourceParams($source, BunnyStream::class, [
                'libraryId' => '$CRAFT_SECURITY_KEY',
                'streamApiKey' => '',
            ]));

            expect($response->data['message'] ?? null)->toBe(VIDEO_PICKER_REFERENCE_ERROR);
        } finally {
            VideoPicker::$plugin->getSources()->deleteSourceById($source->id);
        }
    });
});
