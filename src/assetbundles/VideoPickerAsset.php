<?php
namespace verbb\videopicker\assetbundles;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

use verbb\base\assetbundles\CpAsset as VerbbCpAsset;
use verbb\videopicker\helpers\Plugin as PluginHelper;
use verbb\videopicker\web\assets\cp\CpAsset;

/**
 * @deprecated Use {@see CpAsset} + {@see PluginHelper::registerCpStyles()} instead.
 * Kept as a thin alias while CP assets live under web/assets/cp/.
 */
class VideoPickerAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/videopicker/web/assets/cp/dist';

        $this->depends = [
            VerbbCpAsset::class,
            CraftCpAsset::class,
            CpAsset::class,
        ];

        parent::init();
    }
}
