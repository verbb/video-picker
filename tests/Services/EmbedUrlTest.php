<?php

declare(strict_types=1);

use verbb\videopicker\services\Videos;
use verbb\videopicker\VideoPicker;
use craft\helpers\Json;

it('returns decoded query parameters from an embedded iframe URL', function() {
    $videos = new class extends Videos {
        public function getEmbedData(string $url): array
        {
            return ['code' => '<iframe src="https://player.example.com/video?id=123&amp;token=abc"></iframe>'];
        }
    };

    $url = $videos->getEmbedUrl('https://example.com/video');
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query)->toBe(['id' => '123', 'token' => 'abc']);
});

it('reconstructs remote iframe markup without executable attributes or siblings', function() {
    $videos = new class extends Videos {
        public function getEmbedData(string $url): array
        {
            return [
                'url' => $url,
                'code' => '<iframe src="https://player.example.com/video?id=123&amp;token=abc" onload="alert(1)" srcdoc="<script>alert(2)</script>"></iframe><script>alert(3)</script>',
            ];
        }
    };

    $html = $videos->getEmbedHtml('https://example.com/video', [
        'width' => 640,
        'class' => 'responsive-video',
        'style' => 'aspect-ratio: 16/9',
        'aria-label' => 'Fixture video',
        'data-provider' => 'fixture',
        'onclick' => 'alert(4)',
        'src' => 'javascript:alert(5)',
    ]);
    $dom = new DOMDocument();
    $dom->loadHTML('<div id="embed">' . $html . '</div>');
    $embed = $dom->getElementById('embed');
    $iframe = $embed->getElementsByTagName('iframe')->item(0);

    expect($embed->getElementsByTagName('iframe')->length)->toBe(1)
        ->and($embed->getElementsByTagName('script')->length)->toBe(0)
        ->and($iframe->getAttribute('src'))->toBe('https://player.example.com/video?id=123&token=abc')
        ->and($iframe->getAttribute('width'))->toBe('640')
        ->and($iframe->getAttribute('class'))->toBe('responsive-video')
        ->and($iframe->getAttribute('style'))->toContain('aspect-ratio')
        ->and($iframe->getAttribute('aria-label'))->toBe('Fixture video')
        ->and($iframe->getAttribute('data-provider'))->toBe('fixture')
        ->and($iframe->hasAttribute('onload'))->toBeFalse()
        ->and($iframe->hasAttribute('onclick'))->toBeFalse()
        ->and($iframe->hasAttribute('srcdoc'))->toBeFalse();
});

it('rejects executable iframe sources', function(string $src) {
    $videos = new class($src) extends Videos {
        public function __construct(private string $src)
        {
        }

        public function getEmbedData(string $url): array
        {
            return ['url' => $url, 'code' => '<iframe src="' . $this->src . '"></iframe>'];
        }
    };

    expect($videos->getEmbedHtml('https://example.com/video'))->toBe('');
})->with([
    'javascript:alert(1)',
    'data:text/html,<script>alert(1)</script>',
]);

it('sandboxes non-iframe provider widgets in a data document', function() {
    $videos = new class extends Videos {
        public function getEmbedData(string $url): array
        {
            return ['url' => $url, 'code' => '<blockquote>Video</blockquote><script>alert(1)</script>'];
        }
    };

    $html = $videos->getEmbedHtml('https://example.com/video');
    $dom = new DOMDocument();
    $dom->loadHTML('<div id="embed">' . $html . '</div>');
    $iframe = $dom->getElementById('embed')->getElementsByTagName('iframe')->item(0);

    expect($iframe)->not->toBeNull()
        ->and($iframe->hasAttribute('sandbox'))->toBeTrue()
        ->and($iframe->getAttribute('src'))->toStartWith('data:text/html;charset=utf-8,')
        ->and($dom->getElementsByTagName('script')->length)->toBe(0);
});

it('does not expose sandbox-dependent data documents as standalone embed URLs', function() {
    $videos = new class extends Videos {
        public function getEmbedData(string $url): array
        {
            return ['url' => $url, 'code' => '<blockquote>Video</blockquote><script>alert(1)</script>'];
        }
    };

    expect($videos->getEmbedUrl('https://example.com/video'))->toBeNull();
});

it('sanitizes generic embed markup already stored in cache', function() {
    $url = 'https://example.com/cached-video';
    $settings = VideoPicker::$plugin->getSettings();
    $cacheKey = 'video-picker:embed:' . md5(Json::encode([
        $url,
        $settings->embedAllowedDomains,
        $settings->embedClientSettings,
        $settings->embedDetectorsSettings,
        $settings->resolveHiResEmbedImage,
    ]));
    Craft::$app->getCache()->set($cacheKey, [
        'url' => $url,
        'code' => '<iframe src="https://player.example.com/video"></iframe><script>alert(1)</script>',
    ]);

    try {
        $code = (new Videos())->getEmbedData($url)['code'];

        expect($code)->toContain('<iframe')
            ->and($code)->not->toContain('<script')
            ->and($code)->not->toContain('alert(1)');
    } finally {
        Craft::$app->getCache()->delete($cacheKey);
    }
});

it('preserves a previously sandboxed non-iframe widget in cache', function() {
    $url = 'https://example.com/cached-widget';
    $settings = VideoPicker::$plugin->getSettings();
    $cacheKey = 'video-picker:embed:' . md5(Json::encode([
        $url,
        $settings->embedAllowedDomains,
        $settings->embedClientSettings,
        $settings->embedDetectorsSettings,
        $settings->resolveHiResEmbedImage,
    ]));
    Craft::$app->getCache()->set($cacheKey, [
        'url' => $url,
        'code' => '<iframe src="data:text/html;charset=utf-8,%3Cscript%3Ealert%281%29%3C%2Fscript%3E" sandbox></iframe>',
    ]);

    try {
        $code = (new Videos())->getEmbedData($url)['code'];
        $dom = new DOMDocument();
        $dom->loadHTML('<div id="embed">' . $code . '</div>');
        $iframe = $dom->getElementById('embed')->getElementsByTagName('iframe')->item(0);

        expect($iframe)->not->toBeNull()
            ->and($iframe->hasAttribute('sandbox'))->toBeTrue()
            ->and($iframe->getAttribute('src'))->toStartWith('data:text/html;charset=utf-8,');
    } finally {
        Craft::$app->getCache()->delete($cacheKey);
    }
});
