<?php

declare(strict_types=1);

use verbb\videopicker\sources\Mux;

it('continues through full Mux pages including assets without playback IDs', function() {
    $source = new class extends Mux {
        public array $queries = [];

        public function cachedRequest(string $method = 'GET', string $uri = '', array $options = [])
        {
            $this->queries[] = $options['query'];

            return ['data' => $options['query']['page'] === 1 ? [
                ['id' => 'unpublished', 'playback_ids' => []],
                ['id' => 'published', 'playback_ids' => [['id' => 'playback', 'policy' => 'public']], 'meta' => ['creator_id' => 'fixture']],
            ] : []];
        }
    };
    $first = $source->getVideos('assets', [], 2);
    expect($first['videos'])->toHaveCount(1)
        ->and($first['nextPage'])->toBe(2);
    $last = $source->getVideos('assets', ['nextPage' => $first['nextPage']], 2);
    expect($last['nextPage'])->toBeNull()
        ->and($source->queries)->toBe([['page' => 1, 'limit' => 2], ['page' => 2, 'limit' => 2]]);
});
