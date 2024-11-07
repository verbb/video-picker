<?php
namespace verbb\videopicker\sources;

use verbb\videopicker\base\Source;

use Craft;
use craft\base\MissingComponentInterface;
use craft\base\MissingComponentTrait;

use yii\base\NotSupportedException;

class MissingSource extends Source implements MissingComponentInterface
{
    // Traits
    // =========================================================================

    use MissingComponentTrait;


    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('video-picker', 'Missing Source');
    }

    public static function getOAuthProviderClass(): string
    {
        throw new NotSupportedException('getOAuthProviderClass() is not implemented.');
    }


    // Properties
    // =========================================================================

    public static string $providerHandle = 'missingSource';
}
