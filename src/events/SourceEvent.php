<?php
namespace verbb\videopicker\events;

use verbb\videopicker\base\SourceInterface;

use yii\base\Event;

class SourceEvent extends Event
{
    // Properties
    // =========================================================================

    public SourceInterface $source;
    public bool $isNew = false;

}
