<?php

declare(strict_types=1);

use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\models\Video;
use verbb\videopicker\sources\Vimeo;

it('lets the mute alias override field defaults in either direction', function(bool $default, bool $override) {
    $video = new class extends Video {
        public function getSource(): ?SourceInterface
        {
            return new Vimeo(['handle' => 'vimeo']);
        }
    };
    $video->id = '123456789';
    $video->embedDefaults = ['muted' => $default];
    $options = ['mute' => $override];
    parse_str(parse_url($video->getEmbedUrl($options), PHP_URL_QUERY) ?? '', $query);
    expect($query['muted'])->toBe($override ? '1' : '0')
        ->and($video->getEmbedHtml($options))->toContain('muted=' . (int)$override);
    parse_str(parse_url($video->getEmbedUrl(['mute' => $override, 'muted' => $default]), PHP_URL_QUERY) ?? '', $canonical);
    expect($canonical['muted'])->toBe($default ? '1' : '0');
})->with([[false, true], [true, false]]);
