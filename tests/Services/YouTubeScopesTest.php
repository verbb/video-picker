<?php

declare(strict_types=1);

use verbb\videopicker\sources\YouTube;

it('requests read-only YouTube access', function() {
    $source = new YouTube([
        'scopes' => [
            'https://www.googleapis.com/auth/youtube',
            'custom-scope',
        ],
    ]);
    $scopes = $source->getAuthorizationUrlOptions()['scope'];

    expect($scopes)->toContain('https://www.googleapis.com/auth/youtube.readonly')
        ->and($scopes)->toContain('custom-scope')
        ->and($scopes)->not->toContain('https://www.googleapis.com/auth/youtube');
});
