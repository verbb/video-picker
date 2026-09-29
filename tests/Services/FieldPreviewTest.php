<?php

declare(strict_types=1);

use craft\elements\GlobalSet;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use Tests\Support\AdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\models\Video;
use verbb\videopicker\sources\YouTube;

it('preserves source names and video URLs as text in Craft field previews', function() {
    AdminUser::login();
    $suffix = bin2hex(random_bytes(4));
    $field = new VideoPickerField(['name' => 'Video preview', 'handle' => 'preview' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    $custom = new CustomField($field);
    $layout = new FieldLayout(['type' => GlobalSet::class]);
    $layout->setTabs([new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'elements' => [$custom]])]);
    expect(Craft::$app->getFields()->saveLayout($layout))->toBeTrue();
    $set = new GlobalSet(['name' => 'Video preview', 'handle' => 'preview' . $suffix, 'fieldLayoutId' => $layout->id]);
    $set->setFieldLayout($layout);
    $source = new YouTube(['name' => 'Library <Beta> & "Highlights" ' . $suffix, 'handle' => 'previewSource' . $suffix, 'enabled' => false]);
    $sources = VideoPicker::$plugin->getSources();
    $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&label=Summer%20%26%20Winter';

    try {
        expect($sources->saveSource($source))->toBeTrue()
            ->and(Craft::$app->getGlobals()->saveSet($set))->toBeTrue();
        $video = new Video(['id' => 'dQw4w9WgXcQ', 'url' => $url, 'sourceHandle' => $source->handle]);
        $set->setFieldValue($field->handle, $video);
        $html = $custom->previewHtml($set);
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<div id="preview">' . $html . '</div>');
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $preview = $dom->getElementById('preview');
        expect($preview->textContent)->toBe($source->name . ' - ' . $url)
            ->and($preview->getElementsByTagName('*')->length)->toBe(0);
        $set->setFieldValue($field->handle, null);
        expect($custom->previewHtml($set))->toBe('');
    } finally {
        if ($set->id) {
            Craft::$app->getGlobals()->deleteSet($set);
        }
        if ($source->id) {
            $sources->deleteSource($source);
        }
        Craft::$app->getFields()->deleteField($field);
    }
});
