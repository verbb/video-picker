# GraphQL
You can fetch field content via GraphQL for Video Picker fields. The resulting values will be identical to a [Video](docs:developers/video) object.

```json
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
