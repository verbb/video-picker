<?php
namespace verbb\videopicker\base;

use Craft;
use craft\helpers\Json;
use craft\helpers\StringHelper;

use verbb\auth\base\CredentialsProviderInterface;
use verbb\auth\base\CredentialsProviderTrait;

use Psr\Http\Message\ResponseInterface;
use Throwable;

abstract class CredentialsSource extends Source implements CredentialsProviderInterface
{
    // Traits
    // =========================================================================

    use CredentialsProviderTrait {
        request as credentialsHttpRequest;
    }


    // Static Methods
    // =========================================================================

    public static function supportsConnection(): bool
    {
        return true;
    }

    public static function supportsOAuthConnection(): bool
    {
        return false;
    }


    // Public Methods
    // =========================================================================

    public function isConnected(): bool
    {
        return $this->getConnectionCache() === self::CONNECT_SUCCESS;
    }

    public function supportsBrowse(): bool
    {
        return true;
    }

    public function getSettingsHtml(): ?string
    {
        $handle = StringHelper::toKebabCase(static::$providerHandle);

        return Craft::$app->getView()->renderTemplate('video-picker/sources/credentials/_types/' . $handle, [
            'source' => $this,
        ]);
    }

    public function checkConnection(bool $useCache = true): bool
    {
        if ($useCache && $this->getConnectionCache() === self::CONNECT_SUCCESS) {
            return true;
        }

        if (!$this->isConfigured()) {
            $this->setConnectionCache(self::CONNECT_FAIL);

            return false;
        }

        try {
            $success = $this->fetchConnection();
            $this->setConnectionCache($success ? self::CONNECT_SUCCESS : self::CONNECT_FAIL);

            if ($success) {
                // Successful refresh — drop stale explorer/API cache under this account.
                $this->clearExplorerCache();
            }

            return $success;
        } catch (Throwable $e) {
            $this->setConnectionCache(self::CONNECT_FAIL);

            throw $e;
        }
    }

    /**
     * Decode JSON API responses for parity with OAuth `request()` return shape.
     */
    public function request(string $method = 'GET', string $uri = '', array $options = []): mixed
    {
        try {
            /** @var ResponseInterface $response */
            $response = $this->credentialsHttpRequest($method, $uri, $options);
        } catch (Throwable $e) {
            static::apiError($this, $e);
        }

        $body = (string)$response->getBody();

        if ($body === '') {
            return [];
        }

        try {
            return Json::decode($body);
        } catch (Throwable) {
            return $body;
        }
    }


    // Protected Methods
    // =========================================================================

    /**
     * Lightweight API ping used by CP Refresh (Formie check-connection pattern).
     */
    protected function fetchConnection(): bool
    {
        $this->pingCredentials();

        return true;
    }

    /**
     * Validate credentials against the provider API. Throw with a provider message on failure
     * so CP Connect can surface API errors (and devMode stack traces) via Craft AJAX.
     */
    protected function pingCredentials(): void
    {
    }

    /**
     * Pull a human-readable message from common provider error JSON shapes.
     */
    protected function extractApiErrorMessage(mixed $response): ?string
    {
        if (!is_array($response)) {
            return null;
        }

        if (!empty($response['error'])) {
            $error = $response['error'];

            if (is_string($error)) {
                return $error;
            }

            if (is_array($error)) {
                return $error['message']
                    ?? $error['error_description']
                    ?? $error['error']
                    ?? Json::encode($error);
            }
        }

        if (!empty($response['message']) && ($response['success'] ?? null) === false) {
            return (string)$response['message'];
        }

        if (!empty($response['errors']) && is_array($response['errors'])) {
            $first = reset($response['errors']);

            if (is_string($first)) {
                return $first;
            }

            if (is_array($first)) {
                return $first['message'] ?? Json::encode($first);
            }
        }

        return null;
    }

    /**
     * @param callable(array): bool|null $isValid Optional shape check after error extraction.
     */
    protected function assertApiResponse(mixed $response, string $providerName, ?callable $isValid = null): void
    {
        if (!is_array($response)) {
            throw new \Exception(Craft::t('video-picker', 'Unexpected response from {provider}.', [
                'provider' => $providerName,
            ]));
        }

        if ($message = $this->extractApiErrorMessage($response)) {
            throw new \Exception(Craft::t('video-picker', '{provider} API error: {message}', [
                'provider' => $providerName,
                'message' => $message,
            ]));
        }

        if ($isValid && !$isValid($response)) {
            throw new \Exception(Craft::t('video-picker', 'Unable to connect to {provider}. Check your credentials.', [
                'provider' => $providerName,
            ]));
        }
    }
}
