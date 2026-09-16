<?php

declare(strict_types=1);

use verbb\videopicker\sources\Mux;

it('uses the Mux player start time parameter', function() {
    $source = new Mux(['handle' => 'mux']);
    parse_str(parse_url($source->getEmbedUrl('video-id', ['start' => 12]), PHP_URL_QUERY) ?? '', $query);
    expect($query['start-time'] ?? null)->toBe('12')->and($query)->not->toHaveKey('start');
    expect($source->getEmbedHtml('video-id', ['start' => 0]))->toContain('start-time=0');
});
