# Upgrading to 2.1

Test the update on a copy of your Craft 5 site with a database backup. Keep your existing Video Picker fields and Sources; the update retains their saved URLs and connections. Run Craft's pending migrations as part of deployment before editors resume work.

## Editor Permissions

Grant **Explore videos** to user groups that should browse or resolve videos in fields. This permission is separate from **Sources**, which controls provider management. Existing non-admin editors need the new permission even if they could use Video Picker before the update.

Open an existing entry as an editor, browse a connected Source and resolve a saved URL. Save and reopen the entry to check the selection is retained. Repeat with an empty field and a restricted video used by your site.

## Cache Behaviour

Selected-video metadata now expires according to **Video Cache Duration**, which defaults to seven days. A read after expiry attempts to refresh it, so providers can receive requests while rendering existing content. Check provider connectivity in each deployment environment and adjust the duration under **Settings → Video Picker → Cache Settings** if needed.

The update merges duplicate cache rows for the same URL, retaining the most recently updated copy. Saved field URLs remain unchanged. See [Cache](docs:feature-tour/cache) for refresh controls and unavailable-provider behaviour.

## Custom Source Providers

If a custom provider previously extended `verbb\videopicker\base\Source` and used its OAuth methods, change its parent to `verbb\videopicker\base\OAuthSource`. Keep `getOAuthProviderClass()` on OAuth providers. API-key integrations can extend `CredentialsSource`; the base `SourceInterface` no longer requires an OAuth provider class.

Check custom explorer methods against the [Source reference](docs:developers/source): browse responses contain `videos` and an optional `nextPage`, and request options are limited to `id`, `q` and `nextPage`. Implementations should use `getVideosPerPage()` to respect the field's page-size override.

## Templates and Embed Configuration

Embed 4 is now required. Review custom image detectors and embed client settings against the [configuration reference](docs:get-started/configuration). Test generic URL embeds as well as selected fields, including error fallbacks and any configured allowed domains.

Source embed options now distinguish playback/query parameters from iframe attributes. Check templates that depended on query options also appearing as HTML attributes. Twig calls can override field embed defaults; GraphQL's `embedHtml` and `embedUrl` use the field defaults and accept no arguments.
