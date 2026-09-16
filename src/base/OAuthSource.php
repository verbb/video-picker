<?php
namespace verbb\videopicker\base;

use Craft;
use craft\helpers\UrlHelper;

use verbb\auth\Auth;
use verbb\auth\base\OAuthProviderInterface;
use verbb\auth\base\OAuthProviderTrait;
use verbb\auth\models\Token;

abstract class OAuthSource extends Source implements OAuthProviderInterface
{
    // Traits
    // =========================================================================

    use OAuthProviderTrait;


    // Static Methods
    // =========================================================================

    public static function supportsConnection(): bool
    {
        return true;
    }

    public static function supportsOAuthConnection(): bool
    {
        return true;
    }

    /**
     * Required by Auth {@see OAuthProviderTrait} — not part of the base Source contract.
     */
    abstract public static function getOAuthProviderClass(): string;


    // Properties
    // =========================================================================

    // Set via config files
    public array $authorizationOptions = [];
    public array $scopes = [];


    // Public Methods
    // =========================================================================

    public function settingsAttributes(): array
    {
        // These won't be picked up in a Trait
        $attributes = parent::settingsAttributes();
        $attributes[] = 'clientId';
        $attributes[] = 'clientSecret';
        $attributes[] = 'scopes';

        return $attributes;
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [
            ['clientId', 'clientSecret'], 'required', 'when' => function($model) {
                return $model->enabled;
            },
        ];

        return $rules;
    }

    public function isConfigured(): bool
    {
        return $this->clientId && $this->clientSecret;
    }

    public function isConnected(): bool
    {
        return (bool)$this->getToken();
    }

    public function supportsBrowse(): bool
    {
        return true;
    }

    public function supportsSearch(): bool
    {
        return true;
    }

    public function getRedirectUri(): ?string
    {
        $siteId = Craft::$app->getSites()->getCurrentSite()->id ?? Craft::$app->getSites()->getPrimarySite()->id;

        // Check for Headless Mode and use the Action URL, or when `cpTrigger` is empty to signify split front/back-end
        if (Craft::$app->getConfig()->getGeneral()->headlessMode || !Craft::$app->getConfig()->getGeneral()->cpTrigger) {
            return UrlHelper::cpUrl('video-picker/auth/callback', null, null, $siteId);
        }

        return UrlHelper::siteUrl('video-picker/auth/callback', null, null, $siteId);
    }

    public function getDefaultScopes(): array
    {
        return [];
    }

    public function getAuthorizationUrlOptions(): array
    {
        // Use any auth options defined in config files
        $options = $this->authorizationOptions;

        // Combine default scopes at the provider level, with account level ones, and any in the config.
        $defaultScopes = $this->getOAuthProvider()->defaultScopes();
        $options['scope'] = array_values(array_unique(array_merge($defaultScopes, $this->getDefaultScopes(), $this->scopes)));

        return $options;
    }

    public function getToken(): ?Token
    {
        if ($this->id) {
            $token = Auth::getInstance()->getTokens()->getTokenByOwnerReference('video-picker', $this->id);

            // Also reject stale associations saved before provider changes cleared tokens.
            return $token && $token->providerType === static::class ? $token : null;
        }

        return null;
    }

    public function checkConnection(bool $useCache = true): bool
    {
        return $this->isConnected();
    }
}
