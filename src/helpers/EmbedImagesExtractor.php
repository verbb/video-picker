<?php
namespace verbb\videopicker\helpers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\models\Settings;

use Embed\Detectors\Detector;
use Embed\Detectors\Image;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

class EmbedImagesExtractor extends Detector
{
    private const MAX_CANDIDATES = 5;
    private const MAX_BYTES = 2_000_000;

    // Public Methods
    // =========================================================================

    public function detect(): ?array
    {
        /* @var Settings $settings */
        $settings = VideoPicker::$plugin->getSettings();

        // There are performance concerns, as it requires us to fetch each image, so ensure it's opt-in.
        if (!$settings->resolveHiResEmbedImage) {
            // But always return an array, to ensure it's treated the one way. Fallback to the default
            $image = (new Image($this->extractor))->detect();

            return ['image' => $image];
        }

        $oembed = $this->extractor->getOEmbed();
        $document = $this->extractor->getDocument();
        $metas = $this->extractor->getMetas();
        $ld = $this->extractor->getLinkedData();

        // Find all available images
        $imageUrls = array_values(array_unique(array_filter([
            $oembed->url('image'),
            $oembed->url('thumbnail'),
            $oembed->url('thumbnail_url'),
            $metas->url('og:image', 'og:image:url', 'og:image:secure_url', 'twitter:image', 'twitter:image:src', 'lp:image'),
            $document->link('image_src'),
            $ld->url('image.url'),
            $this->detectFromContentType(),
        ])));

        // Cap candidates — unbounded sequential fetches were a memory/latency footgun.
        $imageUrls = array_slice($imageUrls, 0, self::MAX_CANDIDATES);

        $client = new Client([
            RequestOptions::TIMEOUT => 5,
            RequestOptions::CONNECT_TIMEOUT => 3,
            // Validate every redirect hop against EmbedUrl (SEC-05 secondary fetches).
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 3,
                'strict' => true,
                'referer' => true,
                'on_redirect' => static function($request, $response, $uri) use ($settings): void {
                    EmbedUrl::assertAllowed((string)$uri, $settings->embedAllowedDomains);
                },
            ],
            RequestOptions::HTTP_ERRORS => false,
        ]);

        $largestImage = null;
        $largestSize = 0;

        foreach ($imageUrls as $imageUrl) {
            try {
                EmbedUrl::assertAllowed((string)$imageUrl, $settings->embedAllowedDomains);

                $response = $client->get((string)$imageUrl, [
                    RequestOptions::STREAM => true,
                ]);

                if ($response->getStatusCode() >= 400) {
                    continue;
                }

                $body = $response->getBody();
                $imageContent = '';

                while (!$body->eof() && strlen($imageContent) < self::MAX_BYTES) {
                    $imageContent .= $body->read(65536);
                }

                if ($imageContent === '' || strlen($imageContent) >= self::MAX_BYTES) {
                    continue;
                }

                $sizeInfo = @getimagesizefromstring($imageContent);

                if (!$sizeInfo) {
                    continue;
                }

                [$width, $height] = $sizeInfo;
                $size = $width * $height;

                if ($size > $largestSize) {
                    $largestSize = $size;

                    $largestImage = [
                        'image' => $imageUrl,
                        'imageWidth' => $width,
                        'imageHeight' => $height,
                    ];
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $largestImage;
    }


    // Private Methods
    // =========================================================================

    private function detectFromContentType()
    {
        if (!$this->extractor->getResponse()->hasHeader('content-type')) {
            return null;
        }

        $contentType = $this->extractor->getResponse()->getHeader('content-type')[0];

        if (strpos($contentType, 'image/') === 0) {
            return $this->extractor->getUri();
        }
    }

}
