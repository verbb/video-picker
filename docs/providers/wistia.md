# Wistia
Follow these steps to configure Wistia for Video Picker.

## Connecting to Wistia

### Connect to the Wistia API
1. Go to your <a href="https://wistia.com" target="_blank">Wistia</a> account.
1. In the top-right profile dropdown, select **Settings**.
1. Navigate to **API Home**.
1. Click the **New Token** button.
1. Give the token a **Nickname** and select at least **Read all data** for **Permissions**.
1. Copy the **Access Token** and paste it into the field in Video Picker.
1. Save the source, then click **Connect** to verify the credentials.

### Explorer and URLs
- The video explorer lists **All Medias** and any **Projects** (folders) in your Wistia account.
- **Search** is available in the video explorer.
- Field preview **owner** uses the media's Wistia folder (project) name, with your account name as fallback. Wistia does not expose an uploader or channel on media objects.
- Descriptions are stored as HTML in Wistia; the field preview shows plain text (tags stripped).
- Paste URLs from `*.wistia.com/medias/…` or `fast.wistia.net/embed/iframe/…` into a Video Picker field to resolve videos by URL.
