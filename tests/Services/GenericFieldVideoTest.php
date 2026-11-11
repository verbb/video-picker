<?php

declare(strict_types=1);

use craft\elements\GlobalSet;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\Db;
use craft\helpers\StringHelper;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use Tests\Support\AdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;
use verbb\videopicker\services\Videos;
use verbb\videopicker\sources\YouTube;

class GenericFieldVideos extends Videos
{
    public static array $payload = [];
    public static int $requests = 0;

    public function getEmbedData(string $url): array
    {
        self::$requests++;

        return self::$payload;
    }
}

// Fixture hosts use example.com because the embed host guard resolves DNS.
function genericFieldPayload(string $url, array $overrides = []): array
{
    return array_merge([
        'title' => 'Generic fixture',
        'description' => 'Public video without a source',
        'url' => $url,
        'code' => '<iframe src="https://player.example.com/embed/1" allow="autoplay; fullscreen" allowfullscreen></iframe>',
        'hasProviderCode' => true,
        'authorName' => 'Fixture author',
        'authorUrl' => 'javascript:alert(1)',
        'providerName' => 'Fixture Video',
        'providerUrl' => 'https://example.com',
        'image' => 'https://img.example.com/thumb.jpg',
        'publishedTime' => '2026-09-16T00:00:00+00:00',
    ], $overrides);
}

function withGenericFieldVideos(callable $callback): void
{
    $plugin = VideoPicker::$plugin;
    $original = $plugin->get('videos');
    $plugin->set('videos', new GenericFieldVideos());
    GenericFieldVideos::$requests = 0;

    try {
        $callback();
    } finally {
        $plugin->set('videos', $original);
        GenericFieldVideos::$payload = [];
        GenericFieldVideos::$requests = 0;
    }
}

function genericFieldWithUnconnectedYouTube(string $suffix, callable $callback): void
{
    // Enabled but never connected, so no field can use it.
    $source = new YouTube(['name' => 'Unconnected ' . $suffix, 'handle' => 'unconnected' . $suffix, 'enabled' => true, 'clientId' => 'fixture', 'clientSecret' => 'fixture']);
    $sources = VideoPicker::$plugin->getSources();

    try {
        expect($sources->saveSource($source))->toBeTrue();
        $callback();
    } finally {
        if ($source->id) {
            $sources->deleteSource($source);
        }
    }
}

it('saves a public URL that no source handles as a generic embed', function() {
    AdminUser::login();
    $suffix = bin2hex(random_bytes(4));
    $url = 'https://videos.example.com/watch/' . $suffix;
    $field = new VideoPickerField(['name' => 'Generic video', 'handle' => 'genericVideo' . $suffix, 'publicOnly' => true, 'minDuration' => 10]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    $layout = new FieldLayout(['uid' => StringHelper::UUID(), 'type' => GlobalSet::class]);
    $layout->setTabs([new FieldLayoutTab([
        'layout' => $layout,
        'name' => 'Content',
        'elements' => [new CustomField($field)],
    ])]);
    expect(Craft::$app->getFields()->saveLayout($layout))->toBeTrue();
    $set = new GlobalSet(['name' => 'Generic video', 'handle' => 'genericVideo' . $suffix, 'fieldLayoutId' => $layout->id]);
    $set->setFieldLayout($layout);

    try {
        withGenericFieldVideos(function() use ($set, $field, $url) {
            GenericFieldVideos::$payload = genericFieldPayload($url);

            expect(Craft::$app->getGlobals()->saveSet($set))->toBeTrue();
            $set->setScenario(GlobalSet::SCENARIO_LIVE);
            $set->setFieldValue($field->handle, $url);
            expect(Craft::$app->getElements()->saveElement($set))->toBeTrue()
                ->and($set->getErrors($field->handle))->toBeEmpty();

            $video = $set->getFieldValue($field->handle);
            $record = VideoRecord::findOne(['videoUrl' => $url]);

            expect($video->generic)->toBeTrue()
                ->and($video->sourceHandle)->toBeNull()
                ->and($video->title)->toBe('Generic fixture')
                ->and($video->authorUrl)->toBeNull()
                ->and($video->getThumbnail())->toBe('https://img.example.com/thumb.jpg')
                ->and($video->getVideoData()['providerName'])->toBe('Fixture Video')
                ->and($record)->not->toBeNull()
                ->and($record->videoId)->toBe($url);

            // Later reads and rendering use the stored snapshot, even when the provider is down.
            $requests = GenericFieldVideos::$requests;
            GenericFieldVideos::$payload = ['error' => 'Timed out'];
            $cached = (new GenericFieldVideos())->getVideoByUrl($url, false, $field);
            $html = $cached->getEmbedHtml(['width' => 640, 'onload' => 'alert(1)', 'autoplay' => true]);

            expect($cached->generic)->toBeTrue()
                ->and($cached->title)->toBe('Generic fixture')
                ->and($html)->toContain('src="https://player.example.com/embed/1"')
                ->and($html)->toContain('width="640"')
                ->and($html)->toContain('allowfullscreen')
                ->and($html)->not->toContain('onload')
                ->and($html)->not->toContain('autoplay=')
                ->and($cached->getEmbedUrl(['start' => 5]))->toBe('https://player.example.com/embed/1?start=5')
                ->and(GenericFieldVideos::$requests)->toBe($requests);

            // Template lookups stay limited to configured sources.
            expect((new GenericFieldVideos())->getVideoByUrl($url))->toBeNull();
        });
    } finally {
        if ($set->id) {
            Craft::$app->getGlobals()->deleteSet($set);
        }

        Craft::$app->getFields()->deleteField($field);
        VideoRecord::deleteAll(['videoUrl' => $url]);
    }
});

it('rejects generic URLs without provider player markup', function(array $overrides) {
    $suffix = bin2hex(random_bytes(4));
    $url = 'https://pages.example.com/article/' . $suffix;
    $field = new VideoPickerField(['name' => 'Generic page', 'handle' => 'genericPage' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();

    try {
        withGenericFieldVideos(function() use ($field, $url, $overrides) {
            GenericFieldVideos::$payload = isset($overrides['error'])
                ? ['error' => $overrides['error']]
                : genericFieldPayload($url, $overrides);

            $video = $field->normalizeValue($url);

            expect($video->hasErrors('url'))->toBeTrue()
                ->and($video->generic)->toBeFalse()
                ->and(VideoRecord::findOne(['videoUrl' => $url]))->toBeNull();
        });
    } finally {
        Craft::$app->getFields()->deleteField($field);
        VideoRecord::deleteAll(['videoUrl' => $url]);
    }
})->with([
    'synthesized page iframe' => [['hasProviderCode' => false]],
    'sandboxed widget' => [['code' => '<iframe src="data:text/html;charset=utf-8,%3Cblockquote%3E" sandbox=""></iframe>']],
    'discovery error' => [['error' => 'Embed URL host is not allowed.']],
]);

it('keeps provider URLs restricted to their enabled source', function() {
    $suffix = bin2hex(random_bytes(4));
    $url = 'https://www.youtube.com/watch?v=jfKfPfyJRdk';
    $field = new VideoPickerField(['name' => 'Restricted', 'handle' => 'restricted' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();

    try {
        genericFieldWithUnconnectedYouTube($suffix, function() use ($field, $url) {
            withGenericFieldVideos(function() use ($field, $url) {
                GenericFieldVideos::$payload = genericFieldPayload($url);

                expect($field->normalizeValue($url)->hasErrors('url'))->toBeTrue()
                    ->and(GenericFieldVideos::$requests)->toBe(0);
            });
        });
    } finally {
        Craft::$app->getFields()->deleteField($field);
    }
});

it('rejects short links that resolve to a provider with an enabled source', function(array $overrides) {
    $suffix = bin2hex(random_bytes(4));
    $url = 'https://short.example.com/' . $suffix;
    $field = new VideoPickerField(['name' => 'Short link', 'handle' => 'shortLink' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();

    try {
        genericFieldWithUnconnectedYouTube($suffix, function() use ($field, $url, $overrides) {
            withGenericFieldVideos(function() use ($field, $url, $overrides) {
                GenericFieldVideos::$payload = genericFieldPayload($url, $overrides);

                expect($field->normalizeValue($url)->hasErrors('url'))->toBeTrue()
                    ->and(GenericFieldVideos::$requests)->toBe(1)
                    ->and(VideoRecord::findOne(['videoUrl' => $url]))->toBeNull();
            });
        });
    } finally {
        Craft::$app->getFields()->deleteField($field);
        VideoRecord::deleteAll(['videoUrl' => $url]);
    }
})->with([
    'redirected page URL' => [['url' => 'https://www.youtube.com/watch?v=jfKfPfyJRdk']],
    'provider player' => [['code' => '<iframe src="https://www.youtube.com/embed/jfKfPfyJRdk"></iframe>']],
]);

it('replaces a cached row from a removed source instead of serving it', function() {
    $suffix = bin2hex(random_bytes(4));
    $url = 'https://videos.example.com/watch/' . $suffix;
    $field = new VideoPickerField(['name' => 'Removed source', 'handle' => 'removedSource' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    (new Videos())->saveVideo(new Video([
        'id' => 'private-' . $suffix,
        'url' => $url,
        'sourceHandle' => 'removed' . $suffix,
        'title' => 'Another account private title',
        'private' => true,
    ]));

    try {
        withGenericFieldVideos(function() use ($field, $url) {
            GenericFieldVideos::$payload = genericFieldPayload($url);
            $video = (new GenericFieldVideos())->getVideoByUrl($url, false, $field);

            expect($video->generic)->toBeTrue()
                ->and($video->title)->toBe('Generic fixture')
                ->and($video->private)->toBeFalse()
                ->and(GenericFieldVideos::$requests)->toBe(1);

            // An explicit refresh fetches again rather than reusing the snapshot.
            GenericFieldVideos::$payload = genericFieldPayload($url, ['title' => 'Refreshed fixture']);
            expect((new GenericFieldVideos())->getVideoByUrl($url, true, $field)->title)->toBe('Refreshed fixture')
                ->and(GenericFieldVideos::$requests)->toBe(2);
        });
    } finally {
        Craft::$app->getFields()->deleteField($field);
        VideoRecord::deleteAll(['videoUrl' => $url]);
    }
});

it('rejects generic URLs longer than the video cache key', function() {
    $suffix = bin2hex(random_bytes(4));
    $url = 'https://videos.example.com/watch?' . str_repeat('a', 260);
    $field = new VideoPickerField(['name' => 'Long URL', 'handle' => 'longUrl' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();

    try {
        withGenericFieldVideos(function() use ($field, $url) {
            GenericFieldVideos::$payload = genericFieldPayload($url);

            expect($field->normalizeValue($url)->hasErrors('url'))->toBeTrue()
                ->and(GenericFieldVideos::$requests)->toBe(0);
        });
    } finally {
        Craft::$app->getFields()->deleteField($field);
    }
});

it('keeps the last generic snapshot when revalidation fails', function() {
    $suffix = bin2hex(random_bytes(4));
    $url = 'https://videos.example.com/watch/' . $suffix;
    $field = new VideoPickerField(['name' => 'Generic stale', 'handle' => 'genericStale' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();

    try {
        withGenericFieldVideos(function() use ($field, $url) {
            GenericFieldVideos::$payload = genericFieldPayload($url);
            expect((new GenericFieldVideos())->getVideoByUrl($url, false, $field)->hasErrors())->toBeFalse();

            $record = VideoRecord::findOne(['videoUrl' => $url]);
            $record->expiresAt = Db::prepareDateForDb(new DateTime('-1 day'));
            $record->save(false);
            GenericFieldVideos::$payload = ['error' => 'Timed out'];

            $video = (new GenericFieldVideos())->getVideoByUrl($url, false, $field);

            expect($video->hasErrors())->toBeFalse()
                ->and($video->title)->toBe('Generic fixture')
                ->and($video->getEmbedHtml())->toContain('https://player.example.com/embed/1')
                ->and($video->cacheStatus)->toBe(Videos::CACHE_UNAVAILABLE)
                ->and(VideoRecord::findOne(['videoUrl' => $url])->status)->toBe(Videos::CACHE_UNAVAILABLE);
        });
    } finally {
        Craft::$app->getFields()->deleteField($field);
        VideoRecord::deleteAll(['videoUrl' => $url]);
    }
});
