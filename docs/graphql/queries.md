# Queries

Video Picker doesn’t add its own GraphQL queries. A Video Picker field adds its value to Craft’s element types, so you query it through Craft’s own queries, such as `entries`, the same way as any other custom field. The field returns a `{fieldHandle}_Video` object, or `null` when no video is selected.

## Set Up the Schema

In **GraphQL → Schemas**, edit the schema used by your access token and allow it to read the section that contains your Video Picker field. [Craft’s GraphQL documentation](https://craftcms.com/docs/5.x/development/graphql.html) explains schemas, tokens and endpoints.

## Query a Video

Replace `blog_blog_Entry` and `videoPickerField` below with the generated entry type and field handle from your project.

```graphql
{
    entries(id: 1234) {
        ... on blog_blog_Entry {
            videoPickerField {
                url
                date
                formattedDuration
                plays
                thumbnails
                title
                embedUrl
            }
        }
    }
}
```

A populated field returns data in this shape:

```json
{
    "data": {
        "entries": [
            {
                "videoPickerField": {
                    "url": "https://youtu.be/jfKfPfyJRdk",
                    "date": "2022-07-12T22:12:29+10:00",
                    "formattedDuration": "∞",
                    "plays": 467338277,
                    "thumbnails": [
                        {
                            "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/default_live.jpg",
                            "width": 120,
                            "height": 90
                        },
                        {
                            "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/mqdefault_live.jpg",
                            "width": 320,
                            "height": 180
                        },
                        {
                            "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/hqdefault_live.jpg",
                            "width": 480,
                            "height": 360
                        },
                        {
                            "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/sddefault_live.jpg",
                            "width": 640,
                            "height": 480
                        },
                        {
                            "url": "https://i.ytimg.com/vi/jfKfPfyJRdk/maxresdefault_live.jpg",
                            "width": 1280,
                            "height": 720
                        }
                    ],
                    "title": "lofi hip hop radio 📚 beats to relax/study to",
                    "embedUrl": "https://www.youtube.com/embed/jfKfPfyJRdk?wmode=transparent"
                }
            }
        ]
    }
}
```

`thumbnails` is a JSON scalar, so request it without a nested selection. Each thumbnail has a `url` and may include `width` and `height`. [Interfaces & Types](docs:graphql/interfaces-types) lists every field you can select.

## Embed a Video

Request `embedHtml` for a ready-made iframe, or `embedUrl` to build your own player markup. Both use the embed defaults configured on the Video Picker field, and neither accepts arguments, so change the field’s embed settings to change the player.

```graphql
{
  entries(section: "blog", limit: 1) {
    ... on blog_blog_Entry {
      videoPickerField {
        title
        embedUrl
        embedHtml
      }
    }
  }
}
```

```json
{
  "data": {
    "entries": [
      {
        "videoPickerField": {
          "title": "lofi hip hop radio 📚 beats to relax/study to",
          "embedUrl": "https://www.youtube.com/embed/jfKfPfyJRdk?wmode=transparent",
          "embedHtml": "<iframe src=\"https://www.youtube.com/embed/jfKfPfyJRdk?wmode=transparent\" title=\"External video from youTube\" frameborder=\"0\" allowfullscreen=\"true\" allow=\"autoplay; encrypted-media\"></iframe>"
        }
      }
    ]
  }
}
```

This example uses a source with the handle `youTube`. Attributes set in the field’s embed defaults are added to the iframe or replace these ones.
