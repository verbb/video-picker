<?php
namespace verbb\videopicker\base;

use craft\base\SavableComponentInterface;

interface SourceInterface extends SavableComponentInterface
{
    // Public Methods
    // =========================================================================

    /**
     * Whether this source type has a connect/refresh lifecycle in the CP.
     */
    public static function supportsConnection(): bool;

    /**
     * Whether connection uses OAuth Connect/Disconnect (vs credentials check/refresh).
     */
    public static function supportsOAuthConnection(): bool;

    public function isConfigured(): bool;

    public function isConnected(): bool;

    /**
     * Whether the source can be used for URL resolve / explorer (configured + connected when OAuth).
     */
    public function isUsable(): bool;

    public function supportsBrowse(): bool;

    public function supportsSearch(): bool;

    public function getConnectionStatus(): string;

    /**
     * Verify credentials/API access and cache the result (credentials sources).
     */
    public function checkConnection(bool $useCache = true): bool;
}
