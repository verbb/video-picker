<?php
namespace modules\videopickerdocs;

require_once __DIR__ . '/DocsDemoSource.php';
require_once __DIR__ . '/DocsVimeoSource.php';

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
                $event->types[] = DocsDemoSource::class;
                $event->types[] = DocsVimeoSource::class;
            },
        );
    }
}
