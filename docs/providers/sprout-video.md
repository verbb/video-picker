# Sprout Video
Follow these steps to configure Sprout Video for Video Picker.

## Connecting to Sprout Video

### Connect to the Sprout Video API
1. Go to your <a href="https://sproutvideo.com" target="_blank">Sprout Video</a> account.
1. In the top-right profile dropdown, select **Account Settings**.
1. Navigate to **API**.
1. Copy your **API Key** and paste it into Video Picker.
1. Save the source, then click **Connect** to verify the credentials.

### Explorer and URLs
- The video explorer lists **All Videos** and any **Folders** in your account.
- **Search** filters the current page by title (Sprout has no keyword search API).
- Field preview **owner** uses the video’s folder name when available, otherwise your account **company** name, account holder name, or email from `GET /account`.
- Descriptions are stripped to plain text when stored as HTML.
- Paste URLs from `sproutvideo.com/videos/…` or `videos.sproutvideo.com/embed/…` into a Video Picker field.
