<?php

namespace moca\capture\web\assets\formbuilder;

use craft\web\AssetBundle;
use craft\web\assets\cp\CpAsset;

/**
 * CP assets for the form builder.
 */
class FormBuilderAsset extends AssetBundle
{
    /**
     * @inheritdoc
     */
    public function init(): void
    {
        $this->sourcePath = __DIR__ . '/dist';

        // CpAsset pulls in jQuery, Garnish and Craft's own CP JS, so the builder can rely
        // on all three being present.
        $this->depends = [
            CpAsset::class,
        ];

        $this->js = [
            'capture-formbuilder.js',
        ];

        $this->css = [
            'capture-formbuilder.css',
        ];

        parent::init();
    }
}
