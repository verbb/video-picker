<?php

declare(strict_types=1);

use craft\helpers\Json;
use Tests\Support\AdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;
use verbb\videopicker\sources\Vimeo;

it('preserves Vimeo privacy hashes across model and cached embeds', function(string $url, ?string $hash) {
    AdminUser::login();
    $sources = VideoPicker::$plugin->getSources();
    $source = new Vimeo(['name' => 'Vimeo embed fixture', 'handle' => 'vimeoEmbedFixture', 'enabled' => false]);
    expect($sources->saveSource($source))->toBeTrue();

    try {
        $parser = new ReflectionMethod(Vimeo::class, '_parseVideo');
        $video = $parser->invoke($source, ['uri' => '/videos/123456789', 'link' => $url, 'privacy' => ['view' => 'unlisted']]);
        VideoPicker::$plugin->getVideos()->saveVideo($video);
        $cached = new Video(Json::decode(VideoRecord::findOne(['videoUrl' => $url])->data));

        foreach ([$video, $cached] as $value) {
            parse_str(parse_url($value->getEmbedUrl(['autoplay' => true]), PHP_URL_QUERY) ?? '', $query);
            expect($query['h'] ?? null)->toBe($hash)
                ->and($query['autoplay'])->toBe('1');
            $html = $value->getEmbedHtml(['title' => 'Private & playable']);
            expect($html)->toContain('title="Private &amp; playable"');
            if ($hash) {
                expect($html)->toContain('h=' . $hash)
                    ->and($value->getVideoData()['embedHtml'])->toContain('h=' . $hash);
            }
            expect($value->getEmbedUrl(['h' => 'explicit']))->toContain('h=explicit');
        }
    } finally {
        VideoRecord::deleteAll(['videoUrl' => $url]);
        $sources->deleteSource($source);
    }
})->with([
    'unlisted share link' => ['https://vimeo.com/123456789/913062c8ff', '913062c8ff'],
    'player link' => ['https://player.vimeo.com/video/123456789?h=913062c8ff', '913062c8ff'],
    'public share link' => ['https://vimeo.com/123456789', null],
]);

it('honors explicit Vimeo playback overrides', function() {
    $source = new Vimeo(['handle' => 'vimeo']);
    parse_str(parse_url($source->getEmbedUrl('123456789', ['background' => 1, 'muted' => false, 'autoplay' => false, 'loop' => false, 'controls' => true]), PHP_URL_QUERY) ?? '', $query);
    expect($query)->toMatchArray(['background' => '1', 'muted' => '0', 'autoplay' => '0', 'loop' => '0', 'controls' => '1']);
    expect($source->getEmbedUrl('123456789', ['start' => 12, 'h' => 'hash']))->toBe('https://player.vimeo.com/video/123456789?h=hash#t=12s')
        ->and($source->getEmbedHtml('123456789', ['start' => 0]))->toContain('#t=0s');
});
