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

        // Embed settings
        'resolveHiResEmbedImage' => false,
        'embedClientSettings' => [],
        'embedHeaders' => [],
        'embedDetectorsSettings' => [],
        'embedAllowedDomains' => [],

        'sources' => [],
    ]
];
```

## Configuration options
- `pluginName` - If you wish to customise the plugin name.
- `hasCpSection` - Whether to have the plugin pages appear on the main CP sidebar menu.
- `videosPerPage` - How many videos should be shown per-page when using the video explorer.
- `resolveHiResEmbedImage` - Whether the embed helpers should determine the most hi-resolution image available. Do note that there's performance implications for this, as it requires fetching every available image for the embed data and comparing them.
- `embedClientSettings` - Define any [settings](https://github.com/oscarotero/Embed#settings) to pass to the Curl Client for embed helpers.
- `embedHeaders` - Define any [headers](https://github.com/oscarotero/Embed#settings) to pass to the Curl Client for embed helpers.
- `embedDetectorsSettings` - Define any [settings](https://github.com/oscarotero/Embed#settings) to pass to the detectors for embed helpers.
- `embedAllowedDomains` - Define any allowed domain names for embed helpers. Any embed links that are added _not_ in this list will fail to be saved. Leave empty to allow any domain. Include just the TLD with no `http://`, `https://` or `www`.

### Sources
Supply your client configurations as per the below. The `key` for each item should be the source `handle`.

```php
return [
    '*' => [
        // ...
        'sources' => [
            'vimeo' => [
                'enabled' => true,
                'clientId' => '••••••••••••••••••••••••••••',
                'clientSecret' => '••••••••••••••••••••••••••••',

                // Add in any additional OAuth scopes
                'scopes' => [
                    'video_files',
                ],

                // Add in any additional OAuth authorization options, used when redirecting
                // to the provider to start the OAuth authorization process
                'authorizationOptions' => [
                    'extra' => 'value',
                ],
            ],
            'youTube' => [
                'clientId' => '••••••••••••••••••••••••••••',
                'clientSecret' => '••••••••••••••••••••••••••••',
            ],
        ],
    ],
];
```

## Control Panel
You can also manage configuration settings through the Control Panel by visiting Settings → Video Picker.
