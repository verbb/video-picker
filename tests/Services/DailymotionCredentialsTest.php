<?php

declare(strict_types=1);

use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use verbb\videopicker\sources\Dailymotion;

it('verifies changed Dailymotion secrets before reporting a connection', function(bool $sameInstance, bool $environmentSecret) {
    $tokenRequests = [];
    $originalDefinition = Craft::$container->getDefinitions()[Client::class] ?? null;
    $handler = HandlerStack::create(function(RequestInterface $request) use (&$tokenRequests) {
        if ($request->getUri()->getHost() === 'oauth2.dailymotion.com') {
            parse_str((string)$request->getBody(), $params);
            $tokenRequests[] = $params['client_secret'];

            return Create::promiseFor($params['client_secret'] === 'incorrect'
                ? new Response(401, [], '{"error_description":"Invalid credentials"}')
                : new Response(200, [], '{"access_token":"issued-token","expires_in":1800}'));
        }

        expect($request->getHeaderLine('Authorization'))->toBe('Bearer issued-token');

        return Create::promiseFor(new Response(200, [], '{"user_id":"test-user","profiles":[]}'));
    });
    Craft::$container->set(Client::class, fn($container, $params) => new Client(array_merge($params[0], ['handler' => $handler])));
    $envName = 'VIDEO_PICKER_TEST_DM_SECRET';
    $previousEnv = getenv($envName);
    putenv($envName . '=original');
    $config = ['handle' => 'dmCredentials' . bin2hex(random_bytes(4)), 'apiKey' => 'test-client', 'apiSecret' => $environmentSecret ? '$' . $envName : 'original'];
    $source = new Dailymotion($config);

    try {
        expect($source->checkConnection(false))->toBeTrue();
        $source = $sameInstance ? $source : new Dailymotion($config);
        expect($source->checkConnection(false))->toBeTrue()
            ->and($tokenRequests)->toBe(['original']);

        if ($environmentSecret) {
            putenv($envName . '=incorrect');
        } else {
            $source->apiSecret = 'incorrect';
        }

        expect(fn() => $source->checkConnection(false))->toThrow(Exception::class)
            ->and($source->isConnected())->toBeFalse()
            ->and($tokenRequests)->toBe(['original', 'incorrect']);

        if ($environmentSecret) {
            putenv($envName . '=replacement');
        } else {
            $source->apiSecret = 'replacement';
        }

        expect($source->checkConnection(false))->toBeTrue()
            ->and($source->isConnected())->toBeTrue()
            ->and($tokenRequests)->toBe(['original', 'incorrect', 'replacement']);
    } finally {
        Craft::$container->clear(Client::class);

        if ($originalDefinition !== null) {
            Craft::$container->set(Client::class, $originalDefinition);
        }

        putenv($previousEnv === false ? $envName : $envName . '=' . $previousEnv);
    }
})->with([false, true])->with([false, true]);
