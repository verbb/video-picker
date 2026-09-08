# Cache

Video Picker caches in a few different places so you stay under API limits and keep the control panel snappy.

## Explorer sections

When you browse folders and playlists in the explorer, those section lists are stored in the database indefinitely. Renames or new folders won’t show up until you **Refresh** in the explorer, or clear cache from **Utilities → Video Picker**.

Provider video *pages* (paginated lists / search) use the application cache with TTLs from **Settings → Video Picker → Cache Settings** (`providerCacheDuration` / `providerSearchCacheDuration`). See [Configuration](docs:get-started/configuration).

## Selected videos

Whenever you pick a video, we store a copy of its data in the database, keyed by URL. That snapshot is trusted for **Video Cache Duration** (default 7 days, minimum 1 hour). After it expires, Video Picker revalidates from the provider on the next read and keeps showing the last good snapshot if refresh fails.

Force a refresh with the **Refresh** control on the field, or clear a URL from **Utilities → Video Picker**.

If the same video URL is used in multiple places, they share one cached row.

## Embed helpers

The generic Twig embed helpers (`craft.videoPicker.getEmbedHtml()`, etc.) use a separate embed crawl cache (`embedCacheDuration`). Failed crawls can be cached briefly via `embedErrorCacheDuration` so the same bad URL isn’t retried every request.

## Database vs file cache

Selected-video and explorer section data live in the database so a normal deploy-time file-cache clear doesn’t wipe them. Provider / search / embed TTLs use Craft’s application cache.
