<?php

declare(strict_types=1);

use craft\elements\GlobalSet;
use craft\fieldlayoutelements\CustomField;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use Tests\Support\AdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\records\Video as VideoRecord;
use verbb\videopicker\services\Videos;
use verbb\videopicker\sources\Vimeo;

class MultipleAccountsVimeo extends Vimeo
{
    public static string $videoTitle = 'Second account video';
    public bool $hasVideo = false;

    public function isConfigured(): bool { return true; }
    public function isConnected(): bool { return true; }

    public function request(string $method = 'GET', string $uri = '', array $options = [])
    {
        if (!$this->hasVideo) {
            throw new RuntimeException('Video is not available to this account.');
        }

        $id = basename($uri);
        return [
            'uri' => '/videos/' . $id, 'link' => 'https://vimeo.com/' . $id,
            'name' => self::$videoTitle, 'privacy' => ['view' => 'nobody'],
        ];
    }
}

it('saves and reloads videos available through a later allowed account', function() {
    AdminUser::login();
    $suffix = bin2hex(random_bytes(4));
    $field = new VideoPickerField(['name' => 'Multiple accounts', 'handle' => 'accounts' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    $layout = new FieldLayout(['type' => GlobalSet::class]);
    $layout->setTabs([new FieldLayoutTab(['layout' => $layout, 'name' => 'Content', 'elements' => [new CustomField($field)]])]);
    expect(Craft::$app->getFields()->saveLayout($layout))->toBeTrue();
    $set = new GlobalSet(['name' => 'Multiple accounts', 'handle' => 'accounts' . $suffix, 'fieldLayoutId' => $layout->id]);
    $set->setFieldLayout($layout);
    $sources = VideoPicker::$plugin->getSources();
    $first = new MultipleAccountsVimeo(['name' => 'First ' . $suffix, 'handle' => 'first' . $suffix, 'enabled' => true, 'clientId' => 'fixture', 'clientSecret' => 'fixture', 'fields' => [$field->uid]]);
    $second = new MultipleAccountsVimeo(['name' => 'Second ' . $suffix, 'handle' => 'second' . $suffix, 'enabled' => true, 'clientId' => 'fixture', 'clientSecret' => 'fixture', 'fields' => [$field->uid], 'hasVideo' => true]);
    $url = 'https://vimeo.com/987654321';

    try {
        expect($sources->saveSource($first))->toBeTrue()
            ->and($sources->saveSource($second))->toBeTrue()
            ->and(Craft::$app->getGlobals()->saveSet($set))->toBeTrue();
        $selected = $second->getVideoByUrl($url);
        expect($selected->hasErrors())->toBeFalse();
        // The explorer posts only the selected URL; saving must resolve it again.
        $set->setScenario(GlobalSet::SCENARIO_LIVE);
        $set->setFieldValue($field->handle, $field->serializeValue($selected));
        expect(Craft::$app->getElements()->saveElement($set))->toBeTrue();

        $reloaded = GlobalSet::find()->id($set->id)->one();
        $video = $reloaded->getFieldValue($field->handle);
        expect($video->id)->toBe('987654321')
            ->and($video->sourceHandle)->toBe($second->handle)
            ->and($video->hasErrors())->toBeFalse();

        MultipleAccountsVimeo::$videoTitle = 'Updated remote title';
        $refreshed = (new Videos())->getVideoByUrl($url, true, $field);
        expect($refreshed->id)->toBe('987654321')
            ->and($refreshed->title)->toBe('Updated remote title')
            ->and($refreshed->hasErrors())->toBeFalse();

        // An unavailable later account must still report failure, not a successful empty value.
        $second->hasVideo = false;
        expect($sources->saveSource($second))->toBeTrue();
        $failed = (new Videos())->getVideoByUrl($url, true, $field);
        expect($failed->hasErrors('url'))->toBeTrue();
    } finally {
        MultipleAccountsVimeo::$videoTitle = 'Second account video';
        if ($set->id) {
            Craft::$app->getGlobals()->deleteSet($set);
        }
        foreach ([$first, $second] as $source) {
            if ($source->id) {
                $sources->deleteSource($source);
            }
        }
        VideoRecord::deleteAll(['videoUrl' => $url]);
        Craft::$app->getFields()->deleteField($field);
    }
});
