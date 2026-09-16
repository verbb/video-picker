<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;
use verbb\videopicker\services\Videos;
use verbb\videopicker\sources\YouTube;

it('preserves cached video embeds and expiry when a source handle changes', function() {
    AdminUser::login();
    $sources = VideoPicker::$plugin->getSources();
    $videos = VideoPicker::$plugin->getVideos();
    $source = new YouTube(['name' => 'Rename source', 'handle' => 'renameOriginal', 'enabled' => false]);
    expect($sources->saveSource($source))->toBeTrue();
    $url = 'https://www.youtube.com/watch?v=rename12345';

    try {
        $videos->saveVideo(new Video([
            'id' => 'rename12345', 'url' => $url, 'title' => 'Preserved metadata 📚',
            'sourceHandle' => $source->handle,
        ]));
        $before = VideoRecord::findOne(['videoUrl' => $url]);
        // Populate request memo before saving the source, as a module can do.
        expect($videos->getVideoByUrl($url)->getEmbedUrl())->not->toBeNull();
        $source->handle = 'renameUpdated';
        expect($sources->saveSource($source))->toBeTrue();

        foreach ([$videos, new Videos()] as $reader) {
            $video = $reader->getVideoByUrl($url);
            expect($video->sourceHandle)->toBe('renameUpdated')
                ->and($video->title)->toBe('Preserved metadata 📚')
                ->and($video->getEmbedUrl())->toContain('/embed/rename12345');
        }

        $after = VideoRecord::findOne(['videoUrl' => $url]);
        expect($after->expiresAt)->toBe($before->expiresAt)
            ->and($after->fetchedAt)->toBe($before->fetchedAt);
    } finally {
        VideoRecord::deleteAll(['videoUrl' => $url]);
        $sources->deleteSource($source);
    }
});
