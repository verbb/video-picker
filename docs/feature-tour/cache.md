# Cache
Video Picker has two levels of caching to prevent API limits from being hit, and good performance, generally.

## Source Caching
The Video Picker field allows you to browser folders and favourites for each source. As such, we query the respective APIs for this data, which is then cached indefinitely. Any changes to your folders such as renaming them, or adding them won't appear as "live".

There are two ways to address this - firstly, when the explorer is open, you'll find a **Refresh** button, that will ensure your sources are refreshed from the API. You can also use our **Cache Utility** found in Utilities → Video Picker for on-demand cache-clearing.

The videos for each source are **not** cached, as these are paginated, and cannot reliably be cached.

## Video Caching
Whenever you pick a video, we store a copy of it's data to the database, keyed by its URL. This is cached indefinitely. So, if you change anything about a video, like its title or description, you'll need to refresh it on Video Picker's end.

There are two ways to address this - firstly, when viewing a Video Picker field with a video value, you'll find a **Refresh** button, that will ensure the video is refreshed from the API. You can also use our **Cache Utility** found in Utilities → Video Picker for on-demand cache-clearing of specific URLs.

The benefit of this is that if you use a particular video multiple times in your content, you'll be loading it from the cache. Likewise, even when the cache is cleared, you'll only be making a single API call to fetch that video

## Database Caching
We utilize database-level caching instead of file-level caching, to ensure that Video Picker caches aren't cleared unnecessarily. It's common (and encouraged) to clear file-level caching when deploying to a server, which can lead to a lot of API calls for videos after a deployment - particularly if you have a large site with lots of videos.

So while our caching is aggressive, we feel that as video content often does not change, it's to their benefit.