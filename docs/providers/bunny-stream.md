# Bunny Stream
Follow these steps to configure Bunny Stream for Video Picker.

## Connecting to Bunny Stream

### Connect to the Bunny Stream API
1. Go to the <a href="https://dash.bunny.net/" target="_blank">Bunny Dashboard</a> and sign in.
1. In the left-hand menu, click **Stream** and select your video library.
1. In the left-hand menu, click **API**.
1. Copy the **Video library ID** and paste into **Library ID** in Video Picker.
1. Copy the **API Key** and paste into **Stream API Key** in Video Picker.
1. Save the source, then click **Connect** to verify the credentials.

### Explorer and URLs
- The video explorer lists **All Videos** and any **Collections** in the library.
- **Search** uses Bunny’s `search` query parameter.
- Paste URLs from `player.mediadelivery.net`, `iframe.mediadelivery.net`, or `video.bunnycdn.com/play/…` into a Video Picker field.
- Field preview **owner** uses the video’s collection name when set, otherwise **Library {id}** for the configured library (the Stream API key cannot read the library display name from Bunny’s core API).
