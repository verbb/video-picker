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

        // Provider / embed / selected-video cache TTLs (seconds)
        'providerCacheDuration' => 3600,
        'providerSearchCacheDuration' => 900,
        'embedCacheDuration' => 3600,
        'embedErrorCacheDuration' => 60,
        'videoCacheDuration' => 604800,

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
- `providerCacheDuration` - How long (seconds) to cache provider API responses such as playlists and video lists.
- `providerSearchCacheDuration` - How long (seconds) to cache provider search results (typically shorter than `providerCacheDuration`).
- `embedCacheDuration` - How long (seconds) to cache generic Twig embed helper crawls.
- `embedErrorCacheDuration` - How long (seconds) to cache a *failed* Twig embed crawl so the same URL is not retried every request. Default 60; set to `0` to disable.
- `videoCacheDuration` - How long (seconds) to trust selected-video metadata stored in the database before revalidating on read. Minimum 3600 (1 hour); default 604800 (7 days). Expired rows still serve the last snapshot (stale-while-revalidate).
- `resolveHiResEmbedImage` - Whether the embed helpers should determine the most hi-resolution image available. Do note that there's performance implications for this, as it requires fetching every available image for the embed data and comparing them.
- `embedClientSettings` - Define any [settings](https://github.com/oscarotero/Embed#settings) to pass to the Curl Client for embed helpers. **Config file only.**
- `embedHeaders` - Define any [headers](https://github.com/oscarotero/Embed#settings) to pass to the Curl Client for embed helpers. **Config file only.**
- `embedDetectorsSettings` - Define any [settings](https://github.com/oscarotero/Embed#settings) to pass to the detectors for embed helpers. **Config file only.**
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
You can also manage most configuration settings through the Control Panel by visiting Settings → Video Picker — **General Settings**, **Cache Settings** (TTLs), and **Embed Settings** (hi-res images, allowed domains). Advanced embed client / header / detector arrays remain config-file only.
