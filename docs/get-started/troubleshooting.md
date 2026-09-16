# Troubleshooting

## A Source Will Not Connect

Confirm the client ID, client secret and redirect URI exactly match the values registered with the provider. OAuth providers also require a public HTTPS callback unless their provider guide documents a local testing option. Save credential changes before clicking **Connect**.

If the provider returns an access or consent error, check its application audience, test-user list and requested scopes. Disconnect and reconnect the Source after changing provider-side access.

## The Explorer Is Empty

Check that the Source is enabled, configured, connected and available to the current field. The editor also needs **Explore videos**. Some providers expose only videos owned by the connected account or token, and private or signed-only assets may not provide thumbnails.

After correcting credentials or provider settings, save the Source to clear its provider and explorer caches. Avoid shortening cache durations solely to work around a connection problem.

## A Pasted URL Is Rejected

The URL must match a configured Source or be supported by the generic embed resolver. Generic helpers accept public HTTP and HTTPS hosts only. If `embedAllowedDomains` is configured, add the provider hostname without its scheme or path and account for any hostname used after redirects.

## A Video Works in the Control Panel but Not on the Site

Confirm that the provider permits embedding on the public site's domain. Check browser console and network errors for provider restrictions, Content Security Policy failures or blocked third-party cookies. For YouTube, confirm whether the Source's Privacy Enhanced Mode matches the domains allowed by your Content Security Policy.
