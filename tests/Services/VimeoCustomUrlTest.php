<?php

declare(strict_types=1);

use Tests\Support\AdminUser;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\records\Video as VideoRecord;
use verbb\videopicker\sources\Vimeo;

class CustomUrlVimeo extends Vimeo
{
    public function isConfigured(): bool { return true; }
    public function isConnected(): bool { return true; }

    public function request(string $method = 'GET', string $uri = '', array $options = [])
    {
        return [
            'uri' => '/videos/' . basename($uri), 'link' => 'https://vimeo.com/fixtureuser/custom-name',
            'name' => 'Custom URL video', 'width' => 1920, 'height' => 1080,
        ];
    }
}

it('stores a URL the Vimeo source can parse back to the same video', function(array $data, string $expected) {
    $parser = new ReflectionMethod(Vimeo::class, '_parseVideo');
    $video = $parser->invoke(new Vimeo(['handle' => 'vimeo']), $data);

    expect($video->url)->toBe($expected)
        ->and((new Vimeo())->getVideoIdFromUrl($video->url))->toBe('123456789');
})->with([
    'ID link' => [['uri' => '/videos/123456789', 'link' => 'https://vimeo.com/123456789'], 'https://vimeo.com/123456789'],
    'unlisted link' => [['uri' => '/videos/123456789', 'link' => 'https://vimeo.com/123456789/913062c8ff'], 'https://vimeo.com/123456789/913062c8ff'],
    'custom URL' => [['uri' => '/videos/123456789', 'link' => 'https://vimeo.com/fixtureuser/custom-name'], 'https://vimeo.com/123456789'],
    'custom URL with numeric user' => [['uri' => '/videos/123456789', 'link' => 'https://vimeo.com/2020films/custom-name'], 'https://vimeo.com/123456789'],
    'unlisted custom URL' => [['uri' => '/videos/123456789:913062c8ff', 'link' => 'https://vimeo.com/fixtureuser/custom-name'], 'https://vimeo.com/123456789/913062c8ff'],
]);

it('saves Vimeo videos that have a custom URL', function() {
    AdminUser::login();
    $suffix = bin2hex(random_bytes(4));
    $field = new VideoPickerField(['name' => 'Custom URL', 'handle' => 'customUrl' . $suffix]);
    expect(Craft::$app->getFields()->saveField($field))->toBeTrue();
    $sources = VideoPicker::$plugin->getSources();
    $source = new CustomUrlVimeo(['name' => 'Custom URL ' . $suffix, 'handle' => 'customUrl' . $suffix, 'enabled' => true, 'clientId' => 'fixture', 'clientSecret' => 'fixture', 'fields' => [$field->uid]]);
    $url = 'https://vimeo.com/123456789';

    try {
        expect($sources->saveSource($source))->toBeTrue();
        $selected = $field->normalizeValue($url);
        $stored = $field->serializeValue($selected);
        $reloaded = $field->normalizeValue($stored);

        expect($stored)->toBe($url)
            ->and($reloaded->hasErrors())->toBeFalse()
            ->and((string)$reloaded->id)->toBe('123456789')
            ->and($reloaded->width)->toBe(1920);
    } finally {
        if ($source->id) {
            $sources->deleteSource($source);
        }
        VideoRecord::deleteAll(['videoUrl' => $url]);
        Craft::$app->getFields()->deleteField($field);
    }
});
