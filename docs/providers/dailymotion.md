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
- **Search** is available in the video explorer when an API key is configured.
- **Channel Uploads** appears in the explorer only when **Channel Username** is set.
- Paste URLs from `dailymotion.com` or `dai.ly` into a Video Picker field to resolve videos by URL.
