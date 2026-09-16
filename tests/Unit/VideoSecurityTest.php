<?php

declare(strict_types=1);

use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\helpers\EmbedUrl;
use verbb\videopicker\helpers\PinnedHttpClient;
use verbb\videopicker\models\Video;
use verbb\videopicker\services\Videos;
use GuzzleHttp\TransferStats;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
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

describe('Pinned embed HTTP client', function() {
    it('pins every redirect hop and keeps peer-IP transfer statistics available', function() {
        $resolved = [];
        $requests = [];
        $client = new PinnedHttpClient(
            ['example.com'],
            [],
            function(string $url) use (&$resolved): array {
                $resolved[] = $url;

                return ['93.184.216.34'];
            },
            function(Request $request, array $options) use (&$requests): Response {
                $options['on_stats'](new TransferStats(
                    $request,
                    new Response(200),
                    0.01,
                    null,
                    ['primary_ip' => '93.184.216.34'],
                ));
                $requests[] = [
                    'url' => (string)$request->getUri(),
                    'resolve' => $options['curl'][CURLOPT_RESOLVE] ?? [],
                    'maxFileSize' => $options['curl'][CURLOPT_MAXFILESIZE] ?? null,
                    'stream' => $options['stream'] ?? null,
                ];

                if (count($requests) === 1) {
                    return new Response(302, ['Location' => '/next']);
                }

                return new Response(200);
            },
        );

        $response = $client->sendRequest(new Request('GET', 'https://example.com/start'));

        expect($response->getStatusCode())->toBe(200)
            ->and($resolved)->toBe([
                'https://example.com/start',
                'https://example.com/next',
            ])
            ->and($requests[0]['resolve'])->toBe(['example.com:443:93.184.216.34'])
            ->and($requests[1]['resolve'])->toBe(['example.com:443:93.184.216.34'])
            ->and($requests[0]['maxFileSize'])->toBe(5_000_000)
            ->and($requests[0]['stream'])->toBeFalse();
    });

    it('rejects a disallowed redirect before opening the second connection', function() {
        $requests = [];
        $client = new PinnedHttpClient(
            ['example.com'],
            [],
            function(string $url, array $allowedDomains): array {
                EmbedUrl::assertAllowed($url, $allowedDomains);

                return ['93.184.216.34'];
            },
            function(Request $request) use (&$requests): Response {
                $requests[] = (string)$request->getUri();

                return new Response(302, ['Location' => 'http://127.0.0.1/internal']);
            },
        );

        expect(fn() => $client->sendRequest(new Request('GET', 'https://example.com/start')))
            ->toThrow(InvalidArgumentException::class)
            ->and($requests)->toBe(['https://example.com/start']);
    });

    it('rejects a connection whose actual peer differs from the validated address', function() {
        $client = new PinnedHttpClient(
            ['example.com'],
            [],
            fn(): array => ['93.184.216.34'],
            function(Request $request, array $options): Response {
                $options['on_stats'](new TransferStats(
                    $request,
                    new Response(200),
                    0.01,
                    null,
                    ['primary_ip' => '127.0.0.1'],
                ));

                return new Response(200);
            },
        );

        expect(fn() => $client->sendRequest(new Request('GET', 'https://example.com/start')))
            ->toThrow(RuntimeException::class, 'validated address');
    });

    it('aborts responses that exceed the byte budget even without a content length', function() {
        $client = new PinnedHttpClient(
            ['example.com'],
            ['max_bytes' => 1024],
            fn(): array => ['93.184.216.34'],
            function(Request $request, array $options): Response {
                $options['progress'](0, 1025);

                return new Response(200);
            },
        );

        expect(fn() => $client->sendRequest(new Request('GET', 'https://example.com/start')))
            ->toThrow(RuntimeException::class, 'download limit');
    });

    it('does not forward credentials across allowed cross-origin redirects', function() {
        $requests = [];
        $client = new PinnedHttpClient(
            ['example.com'],
            [],
            fn(): array => ['93.184.216.34'],
            function(Request $request, array $options) use (&$requests): Response {
                $options['on_stats'](new TransferStats(
                    $request,
                    new Response(200),
                    0.01,
                    null,
                    ['primary_ip' => '93.184.216.34'],
                ));
                $requests[] = $request;

                return count($requests) === 1
                    ? new Response(302, ['Location' => 'https://cdn.example.com/video'])
                    : new Response(200);
            },
        );
        $request = (new Request('GET', 'https://example.com/start'))
            ->withHeader('Authorization', 'Bearer secret')
            ->withHeader('Cookie', 'session=secret')
            ->withHeader('X-Trace', 'keep');

        expect($client->sendRequest($request)->getStatusCode())->toBe(200)
            ->and($requests)->toHaveCount(2)
            ->and($requests[1]->hasHeader('Authorization'))->toBeFalse()
            ->and($requests[1]->hasHeader('Cookie'))->toBeFalse()
            ->and($requests[1]->getHeaderLine('X-Trace'))->toBe('keep');
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
