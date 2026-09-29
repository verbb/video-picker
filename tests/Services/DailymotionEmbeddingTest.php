<?php

declare(strict_types=1);

use verbb\videopicker\sources\Dailymotion;

it('maps supported Dailymotion default player options', function() {
    $source = new Dailymotion(['handle' => 'dailymotion']);
    parse_str(parse_url($source->getEmbedUrl('x84sh87', ['start' => 15, 'loop' => true]), PHP_URL_QUERY) ?? '', $query);
    expect($query)->toMatchArray(['startTime' => '15', 'loop' => 'true'])->and($query)->not->toHaveKey('start');
    parse_str(parse_url($source->getEmbedUrl('x84sh87', ['loop' => false, 'autoplay' => true, 'muted' => true, 'controls' => false]), PHP_URL_QUERY) ?? '', $unsupported);
    expect($unsupported)->toBe(['loop' => 'false']);
});
