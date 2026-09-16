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

After connecting, open a Video Picker field and browse **Assets**. Select a known asset with a public playback ID, save the entry and check its thumbnail and player.

Assets with only signed playback IDs do not have a thumbnail available through this integration. Verify that your chosen assets can be played with their intended access settings before making the Source available to editors.

You can paste URLs from `player.mux.com` or `stream.mux.com`. The preview uses creator metadata or the account's organisation name for the owner. Mux videos do not supply a description.
