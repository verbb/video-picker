<?php

declare(strict_types=1);

use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\sources\BunnyStream;

it('honors Bunny playback defaults and start time', function() {
    $source = new BunnyStream(['handle' => 'bunny', 'libraryId' => '123']);
    $field = new VideoPickerField();
    parse_str(parse_url($source->getEmbedUrl('video-id', $field->getEmbedIntentDefaults()), PHP_URL_QUERY) ?? '', $query);
    expect($query['autoplay'] ?? null)->toBe('0')->and($query['muted'] ?? null)->toBe('0')->and($query['loop'] ?? null)->toBe('0');
    parse_str(parse_url($source->getEmbedUrl('video-id', ['autoplay' => true, 'muted' => true, 'loop' => true, 'start' => 12]), PHP_URL_QUERY) ?? '', $enabled);
    expect($enabled)->toMatchArray(['autoplay' => '1', 'muted' => '1', 'loop' => '1', 't' => '12'])->and($enabled)->not->toHaveKey('start');
});
