<?php

declare(strict_types=1);

use verbb\videopicker\sources\CloudflareStream;

it('uses the Cloudflare player start time parameter', function() {
    $source = new CloudflareStream(['handle' => 'cloudflare']);
    parse_str(parse_url($source->getEmbedUrl('video-id', ['start' => 12, 'autoplay' => false]), PHP_URL_QUERY) ?? '', $query);
    expect($query['startTime'] ?? null)->toBe('12')->and($query)->not->toHaveKeys(['start', 'autoplay']);
    parse_str(parse_url($source->getEmbedUrl('video-id', ['start' => 0, 'startTime' => '30s']), PHP_URL_QUERY) ?? '', $override);
    expect($override['startTime'])->toBe('30s');
});
