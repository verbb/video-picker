<?php
namespace verbb\videopicker\web\assets\sourceconnect;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset as CraftCpAsset;

use verbb\base\assetbundles\CpAsset as VerbbCpAsset;

class SourceConnectAsset extends AssetBundle
{
    // Public Methods
    // =========================================================================

    public function init(): void
    {
        $this->sourcePath = '@verbb/videopicker/web/assets/source-connect/dist';

        $this->depends = [
            VerbbCpAsset::class,
            CraftCpAsset::class,
        ];

        parent::init();
    }
}
