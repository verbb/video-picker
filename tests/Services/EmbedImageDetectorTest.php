<?php

declare(strict_types=1);

use Embed\Detectors\Image;
use Embed\Extractor;
use Embed\ExtractorFactory;
use Embed\Http\Crawler;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Uri;
use Psr\Http\Message\UriInterface;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\helpers\EmbedImagesExtractor;

class AdapterImageDetector extends Image
{
    public function detect(): ?UriInterface
    {
        return new Uri('https://images.example.com/full-size.jpg');
    }
}

// Stands in for site adapters with their own image detector, like Vimeo and YouTube since Embed 4.7.
class ImageDetectorAdapter extends Extractor
{
    public function createCustomDetectors(): array
    {
        return ['image' => new AdapterImageDetector($this)];
    }
}

it('keeps embed image metadata as an array when a site adapter has its own image detector', function() {
    $settings = VideoPicker::$plugin->getSettings();
    $resolveHiRes = $settings->resolveHiResEmbedImage;
    $settings->resolveHiResEmbedImage = false;
    $uri = new Uri('https://videos.example.com/watch/1');

    try {
        $factory = new ExtractorFactory();
        $factory->addAdapter('videos.example.com', ImageDetectorAdapter::class);
        $factory->addDetector(EmbedImagesExtractor::NAME, EmbedImagesExtractor::class);
        $extractor = $factory->createExtractor(
            $uri,
            new Request('GET', $uri),
            new Response(200, ['content-type' => 'text/html'], '<html><head><title>Video</title></head></html>'),
            new Crawler(),
        );
        $image = $extractor->{EmbedImagesExtractor::NAME};

        expect($image)->toBeArray()
            ->and((string)$image['image'])->toBe('https://images.example.com/full-size.jpg');
    } finally {
        $settings->resolveHiResEmbedImage = $resolveHiRes;
    }
});
