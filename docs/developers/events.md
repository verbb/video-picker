# Events
Video Picker provides a collection of events for extending its functionality. Modules and plugins can register event listeners, typically in their `init()` methods, to modify Video Picker’s behaviour.

## Source Events

### The `beforeSaveSource` Event
The event that is triggered before a source is saved.

```php
use verbb\videopicker\events\SourceEvent;
use verbb\videopicker\services\Sources;
use yii\base\Event;

Event::on(Sources::class, Sources::EVENT_BEFORE_SAVE_SOURCE, function(SourceEvent $event) {
    $source = $event->source;
    $isNew = $event->isNew;
    \Craft::info("Preparing to save Video Picker Source {$source->handle}.", __METHOD__);
});
```

### The `afterSaveSource` Event
The event that is triggered after a source is saved.

```php
use verbb\videopicker\events\SourceEvent;
use verbb\videopicker\services\Sources;
use yii\base\Event;

Event::on(Sources::class, Sources::EVENT_AFTER_SAVE_SOURCE, function(SourceEvent $event) {
    $source = $event->source;
    $isNew = $event->isNew;
    \Craft::info("Saved Video Picker Source {$source->handle}.", __METHOD__);
});
```

### The `beforeDeleteSource` Event
The event that is triggered before a source is deleted.

```php
use verbb\videopicker\events\SourceEvent;
use verbb\videopicker\services\Sources;
use yii\base\Event;

Event::on(Sources::class, Sources::EVENT_BEFORE_DELETE_SOURCE, function(SourceEvent $event) {
    $source = $event->source;
    \Craft::info("Preparing to delete Video Picker Source {$source->handle}.", __METHOD__);
});
```

### The `afterDeleteSource` Event
The event that is triggered after a source is deleted.

```php
use verbb\videopicker\events\SourceEvent;
use verbb\videopicker\services\Sources;
use yii\base\Event;

Event::on(Sources::class, Sources::EVENT_AFTER_DELETE_SOURCE, function(SourceEvent $event) {
    $source = $event->source;
    \Craft::info("Deleted Video Picker Source {$source->handle}.", __METHOD__);
});
```
