<?php

declare(strict_types=1);

use verbb\videopicker\sources\YouTube;

it('returns empty YouTube search and playlist pages without requesting missing video IDs', function(string $method) {
    $source = new class extends YouTube {
        public function cachedRequest(string $method = 'GET', string $uri = '', array $options = []): mixed
        {
            if ($uri === 'youtube/v3/videos') {
                throw new RuntimeException('No filter selected: video IDs are empty.');
            }

            return ['items' => [], 'nextPageToken' => 'next-page'];
        }
    };

    expect($source->getVideos($method, ['q' => 'no results', 'id' => 'empty-playlist']))
        ->toBe(['videos' => [], 'nextPage' => 'next-page']);
})->with(['search', 'playlist']);
