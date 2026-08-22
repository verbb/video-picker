# Cloudflare Stream
Follow these steps to configure Cloudflare Stream for Video Picker.

## Connecting to Cloudflare Stream

### Connect to the Cloudflare API
1. Go to the <a href="https://dash.cloudflare.com/" target="_blank">Cloudflare Dashboard</a> and sign in.
1. In the left-hand menu, click **Stream**.
1. Copy the **Account ID** and paste into **Account ID** in Video Picker.
1. Go to **My Profile** → **API Tokens** → **Create Token**.
1. Click the **Get Started** button next to **Create Custom Token**.
1. Add an **Account** → **Stream** → **Read** permission.
1. Copy the token and paste it into **API Token** in Video Picker.
1. Save the source, then click **Connect** to verify the credentials.

### Explorer and URLs
- The video explorer lists **Videos** from your Stream library.
- **Search** uses Cloudflare’s `search` query parameter (matches video names).
- Thumbnails and preview URLs come from the Stream API response. Signed-only videos may not show thumbnails without token signing.
- **Owner** uses the video’s `creator` ID when set (Cloudflare’s creator field on upload), optional `meta.creator` / `meta.author`, otherwise your Cloudflare account name or **Cloudflare Stream**.
- Paste URLs from `videodelivery.net`, `iframe.videodelivery.net`, or `*.cloudflarestream.com` into a Video Picker field.
