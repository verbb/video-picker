# Cache
Video Picker has two levels of caching to prevent API limits from being hit, and good performance, generally.

## Source Caching
The Video Picker field allows you to browser folders and favourites for each source. As such, we query the respective APIs for this data, which is then cached indefinitely. Any changes to your folders such as renaming them, or adding them won't appear as "live".

There are two ways to address this - firstly, when the explorer is open, you'll find a **Refresh** button, that will ensure your sources are refreshed from the API. You can also use our **Cache Utility** found in Utilities → Video Picker for on-demand cache-clearing.

The videos for each source are **not** cached, as these are paginated, and cannot reliably be cached.

## Video Caching
Whenever you pick a video, we store a copy of its data in the database, keyed by its URL. That snapshot is trusted for a configurable duration (**Video Cache Duration**, default 7 days). After it expires, Video Picker revalidates from the provider on the next read and keeps showing the last good snapshot if refresh fails (stale-while-revalidate).

You can still force a refresh: use the **Refresh** control on a Video Picker field, or **Cache Utility** → clear a specific URL under Utilities → Video Picker.

The benefit of this is that if you use a particular video multiple times in your content, you'll be loading it from the cache. Likewise, even when the cache is cleared, you'll only be making a single API call to fetch that video.
## Database Caching
We utilize database-level caching instead of file-level caching, to ensure that Video Picker caches aren't cleared unnecessarily. It's common (and encouraged) to clear file-level caching when deploying to a server, which can lead to a lot of API calls for videos after a deployment - particularly if you have a large site with lots of videos.

So while our caching is aggressive, we feel that as video content often does not change, it's to their benefit.