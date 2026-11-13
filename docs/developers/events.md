# Events

Video Picker provides events for extending its functionality. Modules and plugins can register event listeners, typically in their `init()` methods, to change Video Picker’s behaviour.

## Source Type Events

### The `registerSourceTypes` Event
The event that is triggered when Video Picker builds the list of available source types. Append your class to `$event->types` to register it. [Custom Sources](docs:developers/custom-sources) shows a complete source class.

```php
use craft\events\RegisterComponentTypesEvent;
use modules\sitemodule\MySourceProvider;
use verbb\videopicker\services\Sources;
use yii\base\Event;

Event::on(Sources::class, Sources::EVENT_REGISTER_SOURCE_TYPES, function(RegisterComponentTypesEvent $event) {
    $event->types[] = MySourceProvider::class;
});
```

## Source Events

None of the source events can be cancelled. `$event->source` is the source being saved or deleted.

### The `beforeSaveSource` Event
The event that is triggered before a source is saved, and before it’s validated. A listener can change the source’s settings, and the changes are validated and saved with it. `$event->isNew` is `true` for a new source.

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
The event that is triggered after a source is saved. `$event->isNew` is `true` when the source was just created.

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
The event that is triggered before a source is deleted, and before its OAuth token and cached data are removed.

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
