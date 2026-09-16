# Configuration

You can customise Video Picker’s settings using a PHP configuration file. This is optional: each setting has a default, so you only need to include the values you want to change.

To override a setting, create `video-picker.php` in your Craft project’s `/config` directory and return an array of setting names and values. For example, the following will show 24 videos per page in the explorer:

```php
<?php

return [
    'videosPerPage' => 24,
];
```

All other settings keep their defaults. Add any further settings you want to change to the same array. The options below explain the available settings and their defaults.

Values in `config/video-picker.php` override the corresponding values saved in the control panel. To manage a setting through the control panel again, remove its override from the file.

## Environment Overrides

Craft can apply different settings to each environment. Use `*` for shared values and a key matching `CRAFT_ENVIRONMENT` for an environment-specific override. For example:

```php
<?php

return [
    '*' => [
        'videosPerPage' => 12,
    ],
    'dev' => [
        'videosPerPage' => 24,
    ],
];
```

This replaces the simple array above. The `dev` values apply only when `CRAFT_ENVIRONMENT` is `dev`; other environments use the shared values. Merge your own overrides into the appropriate array.

## Configuration Options

::: reference
### `pluginName`

**Type:** `string` · **Default:** `'Video Picker'`

The plugin name shown in the Control Panel.
:::


::: reference
### `hasCpSection`

**Type:** `bool` · **Default:** `true`

Whether to have the plugin pages appear on the main CP sidebar menu.
:::


::: reference
### `videosPerPage`

**Type:** `int` · **Default:** `12`

How many videos should be shown per page in the video explorer.
:::


::: reference
### `providerCacheDuration`

**Type:** `int` · **Default:** `3600`

How long (seconds) to cache provider API responses such as playlists and video lists.
:::


::: reference
### `providerSearchCacheDuration`

**Type:** `int` · **Default:** `900`

How long (seconds) to cache provider search results (typically shorter than `providerCacheDuration`).
:::


::: reference
### `embedCacheDuration`

**Type:** `int` · **Default:** `3600`

How long, in seconds, generic embed helpers retain discovered metadata before requesting it again.
:::


::: reference
### `embedErrorCacheDuration`

**Type:** `int` · **Default:** `60`

How long, in seconds, to retain a failed embed lookup before retrying the URL. Set to `0` to disable error caching.
:::


::: reference
### `videoCacheDuration`

**Type:** `int` · **Default:** `604800`

How long, in seconds, to retain selected-video metadata before the next read attempts a refresh. The minimum is 3600 (one hour). If the refresh fails, the last successful copy remains available.
:::


::: reference
### `resolveHiResEmbedImage`

**Type:** `bool` · **Default:** `false`

Whether embed helpers should select the highest-resolution image available. Enabling this adds remote image requests while the helper compares the available images.
:::


::: reference
### `embedClientSettings`

**Type:** `array` · **Default:** `[]`

Define any [settings](https://github.com/oscarotero/Embed#settings) to pass to the Curl Client for embed helpers. **Config file only.**
:::

::: reference
### `embedClientConfig`

**Type:** `array` · **Default:** `[]`

Image-extraction configuration merged with the plugin's minimum image dimensions when `getEmbedClientConfig()` is called. Generic embed HTTP requests use `embedClientSettings`. **Config file only.**
:::


::: reference
### `embedHeaders`

**Type:** `array` · **Default:** `[]`

Define any [headers](https://github.com/oscarotero/Embed#settings) to pass to the Curl Client for embed helpers. **Config file only.**
:::


::: reference
### `embedDetectorsSettings`

**Type:** `array` · **Default:** `[]`

Define any [settings](https://github.com/oscarotero/Embed#settings) to pass to the detectors for embed helpers. **Config file only.**
:::


::: reference
### `embedAllowedDomains`

**Type:** `array` · **Default:** `[]`

Hostnames that generic embed helpers may fetch. Enter a hostname such as `youtube.com`, without a scheme, path or `www` requirement. Subdomains are included. An empty list permits any public HTTP or HTTPS host; private, reserved and loopback addresses remain blocked.
:::


## Restrict Embed Hosts in Production

Generic embed helpers fetch metadata from the supplied URL on your server. If URLs can originate from editors or other untrusted input, set `embedAllowedDomains` to the providers your site supports. Include every hostname that may appear before or after a provider redirect.

```php
return [
    'embedAllowedDomains' => [
        'youtube.com',
        'youtu.be',
        'vimeo.com',
    ],
];
```

Test pasted URLs from every supported provider after enabling the allowlist. A rejected URL produces an `error` result from `getEmbedData()`. Follow the [generic embed example](docs:template-guides/rendering-videos-from-urls#resolve-generic-embed-data) to check it before outputting a player.


## OAuth Source Credentials

Supply OAuth client credentials in `config/video-picker.php` when you want to preconfigure OAuth sources. The key for each item must match the source handle. Keep secrets in environment variables rather than committing them to project config. Credential-based sources are configured in the Control Panel because their API tokens are not stored in project config.

```php
<?php

use craft\helpers\App;

return [
    '*' => [
        'sources' => [
            'vimeo' => [
                'enabled' => true,
                'clientId' => App::env('VIMEO_CLIENT_ID'),
                'clientSecret' => App::env('VIMEO_CLIENT_SECRET'),

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
                'clientId' => App::env('YOUTUBE_CLIENT_ID'),
                'clientSecret' => App::env('YOUTUBE_CLIENT_SECRET'),
            ],
        ],
    ],
];
```

## Control Panel
Visit **Settings → Video Picker** to manage **General Settings**, cache durations under **Cache Settings**, and image quality and allowed domains under **Embed Settings**. The advanced client, header and detector arrays can only be set in the configuration file.
