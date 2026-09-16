# Dailymotion
Follow these steps to configure Dailymotion for Video Picker.

## Connecting to Dailymotion

### Connect to the Dailymotion API
1. Go to the <a href="https://www.dailymotion.com/partner" target="_blank">Dailymotion Partner Portal</a> and sign in with the account that owns the channel you want to browse.
1. In the top-right **Organization Settings** dropdown, select **API Keys**.
1. Click the **Create API Key** button.
1. Select **Private Key**, enter a **Name** and setup permissions with **Read** access.
1. Copy the **API Key** and paste into **API Key** in Video Picker.
1. Copy the **API Secret** and paste into **API Secret** in Video Picker.
1. Optionally, enter a **Channel Username** (profile name without `@`) or **Profile ID** to add a **Channel Uploads** collection to the video explorer.
1. Save the source, then click **Connect** to verify the credentials.

### Explorer and URLs

After connecting, open a Video Picker field and search for a known video. Set **Channel Username** on the Source when editors need a **Channel Uploads** collection to browse. Select a result, save the entry and check its player.

You can also paste a `dailymotion.com` or `dai.ly` video URL into the field.

### Playback Options

Video Picker uses Dailymotion's default iframe player. The neutral `start` and `loop` embed options control its start time and looping. Autoplay, Muted and Show Controls field defaults do not customize this player: Dailymotion requires a configured Player for those settings, and Video Picker does not select a custom Player ID. See Dailymotion's [player migration guide](https://developers.dailymotion.com/v0/reference/migration-guide-new-embed-endpoint) and [initial mute settings](https://developers.dailymotion.com/changelog/web-android-ios-sdks-mute-deprecation) before choosing this provider for a background or muted autoplay video.
