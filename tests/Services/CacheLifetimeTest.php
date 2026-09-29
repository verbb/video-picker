<?php

declare(strict_types=1);

use craft\elements\GlobalSet;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;

it('keeps the fetch time and failure status when content saves a cached video', function() {
    \Tests\Support\AdminUser::login();
    $url = 'https://example.test/cache-lifetime';
    $video = new Video(['id' => 'lifetime', 'url' => $url, 'title' => 'Cached title']);
    $service = VideoPicker::$plugin->getVideos();
    $service->saveVideo($video);
    $record = VideoRecord::findOne(['videoUrl' => $url]);
    $record->fetchedAt = '2026-01-01 00:00:00';
    $record->expiresAt = '2026-01-08 00:00:00';
    $record->status = 'stale';
    $record->lastError = 'Provider unavailable';
    $record->save(false);
    $before = $record->getAttributes();
    $field = new VideoPickerField(['name' => 'Cache lifetime', 'handle' => 'cacheLifetimeFixture']);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    $layout = new FieldLayout(['type' => GlobalSet::class]);
    $layout->setTabs([new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'elements' => [new CustomField($field)]])]);
    $element = new GlobalSet();
    $element->setFieldLayout($layout);
    $element->setFieldValue($field->handle, $video);

    try {
        $field->afterElementSave($element, false);
        expect(VideoRecord::findOne(['videoUrl' => $url])->getAttributes())->toBe($before);
    } finally {
        VideoRecord::deleteAll(['videoUrl' => $url]);
        Craft::$app->getFields()->deleteField($field);
    }
});
