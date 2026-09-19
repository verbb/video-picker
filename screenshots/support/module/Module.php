<?php
namespace modules\videopickerscreenshots;

use craft\events\RegisterComponentTypesEvent;
use verbb\videopicker\services\Sources;
use yii\base\Event;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public function init(): void
    {
        parent::init();

        Event::on(
            Sources::class,
            Sources::EVENT_REGISTER_SOURCE_TYPES,
            static function(RegisterComponentTypesEvent $event): void {
                $event->types[] = ScreenshotVimeoSource::class;
                $event->types[] = ScreenshotYouTubeSource::class;
            },
        );
    }
}
