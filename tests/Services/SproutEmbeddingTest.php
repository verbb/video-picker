<?php

declare(strict_types=1);

use verbb\videopicker\sources\SproutVideo;

it('translates Sprout playback intent into player parameters', function() {
    $source = new SproutVideo(['handle' => 'sprout']);
    parse_str(parse_url($source->getEmbedUrl('video-id/token', ['autoplay' => true, 'muted' => true, 'loop' => true, 'controls' => false, 'start' => 12]), PHP_URL_QUERY) ?? '', $query);
    expect($query)->toMatchArray(['autoPlay' => 'true', 'volume' => '0', 'loop' => 'true', 'showControls' => 'false', 't' => '12'])->and($query)->not->toHaveKeys(['autoplay', 'muted', 'controls', 'start']);
    parse_str(parse_url($source->getEmbedUrl('video-id/token', ['autoplay' => false, 'muted' => false, 'loop' => false, 'controls' => true]), PHP_URL_QUERY) ?? '', $disabled);
    expect($disabled)->toMatchArray(['autoPlay' => 'false', 'volume' => '1', 'loop' => 'false', 'showControls' => 'true']);
});
