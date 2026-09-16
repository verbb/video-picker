<?php

declare(strict_types=1);

use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\models\Video;

it('filters a large provider page within a bounded time', function() {
    $field = (new ReflectionClass(VideoPickerField::class))->newInstanceWithoutConstructor();
    $field->publicOnly = true;
    $field->minDuration = 10;
    $field->maxDuration = 60;
    $videos = [];

    for ($i = 0; $i < 10_000; $i++) {
        $videos[] = new Video([
            'url' => 'https://example.test/video/' . $i,
            'private' => $i % 3 === 0,
            'duration' => $i % 100,
        ]);
    }

    $start = hrtime(true);
    $filtered = $field->applyExplorerVideoPolicy($videos);
    $elapsed = (hrtime(true) - $start) / 1_000_000_000;

    expect($filtered)->not->toBeEmpty()
        ->and(count($filtered))->toBeLessThan(count($videos))
        ->and(array_filter($filtered, static fn(Video $video): bool => $video->private || $video->duration < 10 || $video->duration > 60))->toBe([])
        ->and($elapsed)->toBeLessThan(1.5);
})->group('perf');
