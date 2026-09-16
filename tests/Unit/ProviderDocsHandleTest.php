<?php

declare(strict_types=1);

use verbb\videopicker\sources\BunnyStream;
use verbb\videopicker\sources\CloudflareStream;
use verbb\videopicker\sources\Dailymotion;
use verbb\videopicker\sources\Mux;
use verbb\videopicker\sources\SproutVideo;
use verbb\videopicker\sources\Vimeo;
use verbb\videopicker\sources\Wistia;
use verbb\videopicker\sources\YouTube;

it('maps each provider to its documentation slug', function(string $sourceClass, string $docsHandle) {
    $source = new $sourceClass();

    expect($source->getProviderDocsHandle())->toBe($docsHandle);
})->with([
    [BunnyStream::class, 'bunny-stream'],
    [CloudflareStream::class, 'cloudflare-stream'],
    [Dailymotion::class, 'dailymotion'],
    [Mux::class, 'mux'],
    [SproutVideo::class, 'sprout-video'],
    [Vimeo::class, 'vimeo'],
    [Wistia::class, 'wistia'],
    [YouTube::class, 'youtube'],
]);
