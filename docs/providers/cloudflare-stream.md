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

After connecting, open a Video Picker field and browse **Videos**, or search by video name. Select a known video and check its preview before saving the entry.

You can paste URLs from `videodelivery.net`, `iframe.videodelivery.net` or `*.cloudflarestream.com`. Videos requiring signed access may lack a thumbnail or usable player without provider-specific signing. Test the access settings of the videos your editors will select.

The preview uses the video's creator information when available, falling back to the account name or Cloudflare Stream.
