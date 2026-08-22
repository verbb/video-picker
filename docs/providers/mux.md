# Mux
Follow these steps to configure Mux for Video Picker.

## Connecting to Mux

### Connect to the Mux API
1. Go to the <a href="https://dashboard.mux.com/" target="_blank">Mux Dashboard</a> and sign in.
1. Navigate to **Settings** → **Access Tokens**.
1. Click the **Create Token** button.
1. Give the token a **Name** and select at least **Mux Video** → **Read** for **Permissions**.
1. Copy the **Token ID** and paste into **Token ID** in Video Picker.
1. Copy the **Secret Key** and paste into **Token Secret** in Video Picker.
1. Save the source, then click **Connect** to verify the credentials.

### Explorer and URLs
- The video explorer lists **Assets** from your Mux account.
- Thumbnails use `image.mux.com` with the asset’s **public** playback ID. Assets that only have **signed** playback IDs will not show thumbnails until signed image URLs are supported.
- **Owner** in the field preview uses the asset’s `creator_id` metadata when set; otherwise the Mux **organization name** from your access token.
- Mux does not support a video description field — only `title`, `creator_id`, and `external_id` in asset metadata.
- Paste URLs from `player.mux.com` or `stream.mux.com` into a Video Picker field to resolve videos by URL.
