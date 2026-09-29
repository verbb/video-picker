<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use verbb\videopicker\sources\BunnyStream;
use verbb\videopicker\sources\Dailymotion;

it('does not follow redirects from credential provider APIs', function() {
    $requests = [];
    $originalDefinition = Craft::$container->getDefinitions()[Client::class] ?? null;
    $handler = HandlerStack::create(function(RequestInterface $request) use (&$requests) {
        $requests[] = (string)$request->getUri();

        return Create::promiseFor(new Response(302, ['Location' => 'http://127.0.0.1/internal']));
    });
    Craft::$container->set(Client::class, fn($container, $params) => new Client(array_merge($params[0], ['handler' => $handler])));
    $source = new BunnyStream(['streamApiKey' => 'test-key']);

    try {
        expect($source->request('GET', 'library/123/videos', ['allow_redirects' => true]))->toBe([])
            ->and($requests)->toHaveCount(1)
            ->and($requests[0])->toBe('https://video.bunnycdn.com/library/123/videos');
    } finally {
        Craft::$container->clear(Client::class);

        if ($originalDefinition !== null) {
            Craft::$container->set(Client::class, $originalDefinition);
        }
    }
});

it('does not follow redirects while obtaining Dailymotion credentials', function() {
    $requests = [];
    $originalDefinition = Craft::$container->getDefinitions()[Client::class] ?? null;
    $handler = HandlerStack::create(function(RequestInterface $request) use (&$requests) {
        $requests[] = (string)$request->getUri();

        return Create::promiseFor(new Response(307, ['Location' => 'http://169.254.169.254/latest/meta-data/']));
    });
    Craft::$container->set(Client::class, fn($container, $params) => new Client(array_merge($params[0], ['handler' => $handler])));
    $source = new Dailymotion([
        'handle' => 'redirect-test',
        'apiKey' => 'test-client',
        'apiSecret' => 'test-secret',
    ]);

    try {
        expect(fn() => $source->checkConnection(false))->toThrow(Exception::class)
            ->and($requests)->toBe(['https://oauth2.dailymotion.com/v2/token']);
    } finally {
        Craft::$container->clear(Client::class);

        if ($originalDefinition !== null) {
            Craft::$container->set(Client::class, $originalDefinition);
        }
    }
});
