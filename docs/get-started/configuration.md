# Configuration
Create a `video-picker.php` file under your `/config` directory with the following options available to you. You can also use multi-environment options to change these per environment.

The below shows the defaults already used by Video Picker, so you don't need to add these options unless you want to modify the values.

```php
<?php

return [
    '*' => [
        'pluginName' => 'Video Picker',
        'hasCpSection' => true,
        'videosPerPage' => 12,
    ]
];
```

## Configuration options
- `pluginName` - If you wish to customise the plugin name.
- `hasCpSection` - Whether to have the plugin pages appear on the main CP sidebar menu.
- `videosPerPage` - How many videos should be shown per-page when using the video explorer.

## Control Panel
You can also manage configuration settings through the Control Panel by visiting Settings → Video Picker.
