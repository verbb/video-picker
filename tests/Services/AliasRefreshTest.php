<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\records\Video as VideoRecord;
use verbb\videopicker\services\Videos;
use verbb\videopicker\sources\YouTube;

class AliasRefreshYouTube extends YouTube
{
    public static string $remoteTitle = 'Original title';
    public static int $requests = 0;

    public function isConfigured(): bool { return true; }
    public function isConnected(): bool { return true; }

    public function request(string $method = 'GET', string $uri = '', array $options = [])
    {
        self::$requests++;

        return ['items' => [['id' => 'abcdefghijk', 'snippet' => [
            'title' => self::$remoteTitle, 'publishedAt' => '2026-09-16T00:00:00Z',
        ]]]];
    }
}

it('refreshes provider metadata when an alias has no exact snapshot row', function() {
    AdminUser::login();
    $suffix = bin2hex(random_bytes(4));
    $field = new VideoPickerField(['name' => 'Alias refresh', 'handle' => 'alias' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    $source = new AliasRefreshYouTube(['name' => 'Alias ' . $suffix, 'handle' => 'alias' . $suffix, 'enabled' => true, 'clientId' => 'fixture', 'clientSecret' => 'fixture', 'fields' => [$field->uid]]);
    $sources = VideoPicker::$plugin->getSources();
    $url = 'https://www.youtube.com/watch?v=abcdefghijk';

    try {
        expect($sources->saveSource($source))->toBeTrue();
        $reader = new Videos();
        $first = $reader->getVideoByUrl($url, false, $field);
        expect($first->title)->toBe('Original title')
            ->and($first->url)->toBe('https://youtu.be/abcdefghijk')
            ->and(VideoRecord::findOne(['videoUrl' => $url]))->toBeNull();
        expect($reader->getVideoByUrl($first->url, false, $field)->title)->toBe('Original title');
        AliasRefreshYouTube::$remoteTitle = 'Updated title';
        $refreshed = $reader->getVideoByUrl($url, true, $field);
        expect($refreshed->title)->toBe('Updated title')
            ->and(AliasRefreshYouTube::$requests)->toBe(2)
            ->and($reader->getVideoByUrl($first->url, false, $field)->title)->toBe('Updated title')
            ->and((new Videos())->getVideoByUrl($first->url, false, $field)->title)->toBe('Updated title');
    } finally {
        if ($source->id) {
            $sources->deleteSource($source);
        }

        Craft::$app->getFields()->deleteField($field);
        VideoRecord::deleteAll(['videoUrl' => 'https://youtu.be/abcdefghijk']);
        AliasRefreshYouTube::$remoteTitle = 'Original title';
        AliasRefreshYouTube::$requests = 0;
    }
});
