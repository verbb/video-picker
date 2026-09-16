<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;
use verbb\videopicker\services\Videos;
use verbb\videopicker\sources\BunnyStream;

class LibraryIdentityBunny extends BunnyStream
{
    public function isConfigured(): bool { return true; }
    public function isConnected(): bool { return true; }
    public function request(string $method = 'GET', string $uri = '', array $options = []): mixed
    {
        throw new RuntimeException('Unexpected provider request for a fresh public snapshot.');
    }
}

it('keeps the Bunny library as part of cached video identity', function(bool $changeSettings) {
    AdminUser::login();
    $suffix = bin2hex(random_bytes(4));
    $field = new VideoPickerField(['name' => 'Library identity', 'handle' => 'library' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    $sources = VideoPicker::$plugin->getSources();
    $first = new LibraryIdentityBunny(['name' => 'Library A ' . $suffix, 'handle' => 'libraryA' . $suffix, 'libraryId' => '111', 'streamApiKey' => 'fixture', 'enabled' => true, 'fields' => []]);
    $second = new LibraryIdentityBunny(['name' => 'Library B ' . $suffix, 'handle' => 'libraryB' . $suffix, 'libraryId' => '222', 'streamApiKey' => 'fixture', 'enabled' => true, 'fields' => [$field->uid]]);
    $id = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $url = 'https://player.mediadelivery.net/embed/111/' . $id;

    try {
        expect($sources->saveSource($first))->toBeTrue()
            ->and($sources->saveSource($second))->toBeTrue();
        (new Videos())->saveVideo(new Video(['id' => $id, 'url' => $url, 'sourceHandle' => $first->handle, 'title' => 'Library A video']));

        if ($changeSettings) {
            $first->libraryId = '222';
            expect($sources->saveSource($first))->toBeTrue();
            $cached = (new Videos())->getVideoByUrl($url);
            expect($cached->url)->toBe($url)
                ->and($cached->getEmbedUrl())->toBe($url)
                ->and($cached->getEmbedHtml())->toContain('/embed/111/' . $id);
        } else {
            expect((new Videos())->getVideoByUrl($url, false, $field))->toBeNull();
            // A second account for the same library can safely reuse the public snapshot.
            $second->libraryId = '111';
            expect($sources->saveSource($second))->toBeTrue();
            expect((new Videos())->getVideoByUrl($url, false, $field)->getEmbedUrl())->toBe($url);
        }
    } finally {
        foreach ([$first, $second] as $source) {
            if ($source->id) {
                $sources->deleteSource($source);
            }
        }

        Craft::$app->getFields()->deleteField($field);
        VideoRecord::deleteAll(['videoUrl' => $url]);
    }
})->with([false, true]);

it('matches supported Bunny URL forms only within the configured library', function(string $prefix) {
    $source = new BunnyStream(['libraryId' => '111']);
    $id = 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee';
    $url = $prefix . '/111/' . $id;
    expect($source->getVideoIdFromUrl($url))->toBe($id);
    $source->libraryId = '222';
    expect($source->getVideoIdFromUrl($url))->toBeNull()
        ->and($source->getEmbedUrl($id, $source->getVideoEmbedOptions(new Video(['id' => $id, 'url' => $url]))))
        ->toBe('https://player.mediadelivery.net/embed/111/' . $id);
})->with([
    'https://player.mediadelivery.net/embed',
    'https://iframe.mediadelivery.net/embed',
    'https://video.bunnycdn.com/play',
]);
