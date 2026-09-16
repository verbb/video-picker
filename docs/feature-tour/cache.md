# Cache

Video Picker keeps copies of provider data so editors can browse quickly without requesting the same information repeatedly. Refresh the relevant cache when a video, folder or playlist has changed at the provider.

## Explorer Sections

Folder and playlist lists stay cached until refreshed. If you rename a folder or create a playlist, use **Refresh** in the explorer or clear the cache from **Utilities → Video Picker**, then reopen the collection to check it appears.

Video lists and search results expire automatically. Adjust their durations under **Settings → Video Picker → Cache Settings** when editors need provider changes to appear sooner. Shorter durations make more provider requests; [Configuration](docs:get-started/configuration) gives the defaults.

## Selected Videos

Selected videos retain a copy of their metadata for **Video Cache Duration**, which defaults to seven days and has a minimum of one hour. When that period expires, the next read attempts a refresh. If the provider request fails, Video Picker keeps the last successful copy.

Use **Refresh** on the field after changing a selected video's title or thumbnail, or clear its URL from **Utilities → Video Picker**. The same URL shares cached metadata across fields, so refreshing it can update other entries that use that video.

## Embed Helpers

Generic URL embeds have a separate cache. After changing the remote page or fixing a rejected URL, allow its cached result to expire or clear Craft's application cache before checking again. Failed discovery is cached briefly to avoid repeated requests for the same unavailable URL.

## Database vs File Cache

Clearing Craft's application cache removes cached provider pages, search results and generic embed discovery. It does not remove selected-video metadata or folder and playlist lists, which are stored in the database. Use the Video Picker utility when those need refreshing.
