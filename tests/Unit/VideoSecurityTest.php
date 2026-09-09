<?php

declare(strict_types=1);

use ReflectionClass;
use ReflectionMethod;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\helpers\EmbedUrl;
use verbb\videopicker\models\Video;
use verbb\videopicker\services\Videos;
use yii\base\InvalidArgumentException;

describe('EmbedUrl host policy', function() {
    it('normalizes bracketed IPv6 hosts', function() {
        $host = EmbedUrl::normalizeHost((string)parse_url('http://[::1]/', PHP_URL_HOST));

        expect($host)->toBe('::1');
        expect(filter_var($host, FILTER_VALIDATE_IP))->not->toBeFalse();
        expect(EmbedUrl::isBlockedHost($host))->toBeTrue();
    });

    it('blocks localhost and private IPv4 literals', function() {
        expect(EmbedUrl::isBlockedHost('localhost'))->toBeTrue();
        expect(EmbedUrl::isBlockedHost('127.0.0.1'))->toBeTrue();
        expect(EmbedUrl::isBlockedHost('10.0.0.1'))->toBeTrue();
        expect(EmbedUrl::isBlockedHost('example.com'))->toBeFalse();
    });

    it('enforces optional domain allowlists', function() {
        EmbedUrl::assertAllowed('https://cdn.example.com/v', ['example.com']);

        expect(fn() => EmbedUrl::assertAllowed('https://evil.test/v', ['example.com']))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects non-http schemes', function() {
        expect(fn() => EmbedUrl::assertAllowed('file:///etc/passwd'))
            ->toThrow(InvalidArgumentException::class);
    });
});

describe('VideoPickerField selection policy', function() {
    it('registers element validation for selection policy', function() {
        $field = (new ReflectionClass(VideoPickerField::class))->newInstanceWithoutConstructor();

        expect($field->getElementValidationRules())->toContain('validateVideoSelection');
    });

    it('rejects private videos when publicOnly is enabled', function() {
        $field = (new ReflectionClass(VideoPickerField::class))->newInstanceWithoutConstructor();
        $field->publicOnly = true;

        $private = new Video(['url' => 'https://example.com/v', 'private' => true, 'duration' => 30]);
        $public = new Video(['url' => 'https://example.com/v', 'private' => false, 'duration' => 30]);

        expect($field->selectionPolicyError($private))->not->toBeNull();
        expect($field->selectionPolicyError($public))->toBeNull();
    });

    it('filters explorer results by publicOnly and duration', function() {
        $field = (new ReflectionClass(VideoPickerField::class))->newInstanceWithoutConstructor();
        $field->publicOnly = true;
        $field->minDuration = 10;
        $field->maxDuration = 60;

        $videos = [
            new Video(['url' => 'https://a.test/1', 'private' => true, 'duration' => 20]),
            new Video(['url' => 'https://a.test/2', 'private' => false, 'duration' => 5]),
            new Video(['url' => 'https://a.test/3', 'private' => false, 'duration' => 20]),
            new Video(['url' => 'https://a.test/4', 'private' => false, 'duration' => 90]),
        ];

        $filtered = $field->applyExplorerVideoPolicy($videos);

        expect($filtered)->toHaveCount(1);
        expect($filtered[0]->url)->toBe('https://a.test/3');
    });
});

describe('Videos private shared-cache miss', function() {
    it('returns URL-only error payload without foreign metadata', function() {
        $service = new Videos();
        $method = new ReflectionMethod(Videos::class, '_privateCacheMiss');
        $method->setAccessible(true);

        /** @var Video $video */
        $video = $method->invoke($service, 'https://example.com/private', 'denied', Videos::CACHE_UNAVAILABLE);

        expect($video->url)->toBe('https://example.com/private');
        expect($video->hasErrors())->toBeTrue();
        expect($video->title ?? null)->toBeNull();
        expect($video->cacheStatus)->toBe(Videos::CACHE_UNAVAILABLE);
    });
});
