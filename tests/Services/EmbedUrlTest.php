<?php

declare(strict_types=1);

use verbb\videopicker\services\Videos;

it('returns decoded query parameters from an embedded iframe URL', function() {
    $videos = new class extends Videos {
        public function getEmbedData(string $url): array
        {
            return ['code' => '<iframe src="https://player.example.com/video?id=123&amp;token=abc"></iframe>'];
        }
    };

    $url = $videos->getEmbedUrl('https://example.com/video');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toBe(['id' => '123', 'token' => 'abc']);
});
