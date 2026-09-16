<?php

declare(strict_types=1);

use verbb\videopicker\sources\Wistia;

it('translates Wistia playback intent into its iframe options', function() {
    $source = new Wistia(['handle' => 'wistia']);
    $options = ['autoplay' => true, 'muted' => true, 'loop' => true, 'controls' => false, 'start' => 10, 'title' => 'Player'];
    parse_str(parse_url($source->getEmbedUrl('abcde12345', $options), PHP_URL_QUERY) ?? '', $query);

    expect($query)->toMatchArray(['autoPlay' => 'true', 'muted' => 'true', 'endVideoBehavior' => 'loop', 'time' => '10'])
        ->and($query)->not->toHaveKeys(['autoplay', 'loop', 'controls', 'start', 'title']);
    foreach (['playbar', 'playButton', 'smallPlayButton', 'fullscreenButton', 'volumeControl', 'settingsControl'] as $control) {
        expect($query[$control])->toBe('false');
    }
    expect($source->getEmbedHtml('abcde12345', $options))->toContain('autoPlay=true', 'title="Player"');

    parse_str(parse_url($source->getEmbedUrl('abcde12345', ['autoplay' => false, 'muted' => false, 'loop' => false, 'controls' => true]), PHP_URL_QUERY) ?? '', $disabled);
    expect($disabled)->toMatchArray(['autoPlay' => 'false', 'muted' => 'false', 'endVideoBehavior' => 'default', 'playbar' => 'true']);
    parse_str(parse_url($source->getEmbedUrl('abcde12345', ['autoplay' => true, 'autoPlay' => 'false']), PHP_URL_QUERY) ?? '', $override);
    expect($override['autoPlay'])->toBe('false');
});
