<?php

declare(strict_types=1);

use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;
use verbb\videopicker\services\Videos;
use craft\helpers\Json;
use craft\helpers\StringHelper;

it('preserves literal shortcodes and emoji through repeated metadata cache reads', function() {
    $video = new Video([
        'id' => 'metadata-literals',
        'url' => 'https://example.test/metadata-literals',
        'sourceHandle' => 'literalOriginal',
        'title' => 'Write :smile: literally 😄',
        'description' => ':heart: is text; ❤️ is an emoji. 日本語',
        'authorName' => ':smile: author',
        'raw' => ['url' => 'https://example.test/:smile:', 'nested' => ['literal' => ':heart:', 'emoji' => '📚']],
    ]);
    $expected = $video->toArray();

    try {
        for ($i = 0; $i < 2; $i++) {
            (new Videos())->saveVideo($video);
            $video = (new Videos())->getVideoByUrl($video->url);
            expect($video->toArray())->toBe($expected);
        }

        (new Videos())->renameSourceHandle('literalOriginal', 'literalRenamed');
        $expected['sourceHandle'] = 'literalRenamed';
        expect((new Videos())->getVideoByUrl($video->url)->toArray())->toBe($expected);
    } finally {
        VideoRecord::deleteAll(['videoUrl' => 'https://example.test/metadata-literals']);
    }
});

it('reads legacy emoji snapshots and upgrades their encoding on refresh', function() {
    $video = new Video(['id' => 'legacy-emoji', 'url' => 'https://example.test/legacy-emoji', 'title' => 'Legacy 😄 📚']);

    try {
        (new Videos())->saveVideo($video);
        $record = VideoRecord::findOne(['videoUrl' => $video->url]);
        $record->data = StringHelper::emojiToShortcodes(Json::encode($video));
        $record->dataVersion = 0;
        $record->save(false);
        expect((new Videos())->getVideoByUrl($video->url)->title)->toBe($video->title);

        $video->title = 'Refreshed :smile: text 😄';
        (new Videos())->saveVideo($video);
        expect((new Videos())->getVideoByUrl($video->url)->title)->toBe($video->title)
            ->and((int)VideoRecord::findOne(['videoUrl' => $video->url])->dataVersion)->toBe(1);
    } finally {
        VideoRecord::deleteAll(['videoUrl' => $video->url]);
    }
});
