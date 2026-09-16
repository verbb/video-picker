# YouTube

Create a Google OAuth client so Video Picker can browse videos available to the connected YouTube account.

## Connecting to YouTube

### Connect to the Google API

1. Open the [Google Cloud Console](https://console.cloud.google.com/) and select or create a project.
2. In **APIs & Services → Library**, enable **YouTube Data API v3**.
3. Open **Google Auth Platform → Branding** and complete the application details.
4. Open **Audience**, choose the appropriate internal or external audience, and add the connecting Google account as a test user when the app is in testing.
5. Open **Clients**, create an OAuth client and choose **Web application**.
6. Give the client a name that identifies this Craft environment.
7. Under **Authorised redirect URIs**, add the exact **Redirect URI** shown by the Video Picker Source. Add a URI for each environment that will connect independently.
8. Copy the generated **Client ID** and **Client Secret** into the Video Picker Source and save it.
9. Click **Connect**, authorise the requested YouTube access and confirm the Source reports a connected account.

## Production Connections

For an external Google OAuth app, **Testing** normally limits refresh tokens to seven days. Adding a test user enables the initial connection but does not make it suitable for ongoing production use. See Google's [refresh token expiration rules](https://developers.google.com/identity/protocols/oauth2#expiration).

Before launch, open **Google Auth Platform → Audience** and move an external production app to **In production**. Complete any verification Google requires for its audience and scopes. An internal Google Workspace app follows its organisation's access policies; [Google's app-state guide](https://developers.google.com/identity/protocols/oauth2/production-readiness/overview) explains the distinction.

Reconnect the Video Picker Source after changing the app's publishing status so it receives a new token. Browse the connected account's uploads and select a video to confirm access. If a connection repeatedly expires after a week, check the publishing status before reconnecting again.

## Privacy Enhanced Mode

Enable **Privacy Enhanced Mode** on the YouTube Source to embed with `youtube-nocookie.com` instead of `youtube.com`.

## Local Testing Proxy

When Google will not accept your local callback, enable **Proxy Redirect URI** on the Source and register the generated proxy URL with Google. The proxy sends the OAuth response through Verbb's public endpoint and back to your local Craft URL.

For example, you might have a Redirect URI like the following:

```text
http://my-site.test/video-picker/auth/callback
```

The proxy changes it to:

```text
https://proxy.verbb.io?return=http://my-site.test/video-picker/auth/callback
```

Use the proxy only for local development. Register each production or staging callback directly with Google over HTTPS.


## Troubleshooting

```text
Access blocked: ddev.site has not completed the Google verification process.
```

In Google Auth Platform, open **Audience** and add the Google account used to connect Video Picker under **Test users**. Then restart the connection from the Source.
