<?php

declare(strict_types=1);

use verbb\videopicker\migrations\m261124_000000_redact_cached_error_tokens;
use verbb\videopicker\records\Video as VideoRecord;

it('redacts access tokens from stored video lookup errors', function() {
    $secret = 'fixture1234567890abcdef';
    $rows = [
        'https://example.test/cached-error-token' => 'Client error: `GET https://api.vimeo.com/videos/1?access_token=' . $secret . '&fields=created_time%2Cdescription` resulted in a `404 Not Found` response',
        'https://example.test/cached-error-token-last' => 'Client error: `GET https://api.vimeo.com/videos/2?fields=name&access_token=' . $secret . '` resulted in a `403 Forbidden` response',
        'https://example.test/cached-error-plain' => 'Unable to refresh video metadata.',
    ];

    foreach ($rows as $url => $lastError) {
        Craft::$app->getDb()->createCommand()->insert('{{%video_picker_videos}}', [
            'videoId' => md5($url),
            'videoUrl' => $url,
            'data' => null,
            'dataVersion' => 1,
            'status' => 'unavailable',
            'lastError' => $lastError,
        ])->execute();
    }

    try {
        expect((new m261124_000000_redact_cached_error_tokens())->safeUp())->toBeTrue();

        $stored = VideoRecord::find()
            ->select(['lastError'])
            ->where(['videoUrl' => array_keys($rows)])
            ->indexBy('videoUrl')
            ->column();

        expect($stored['https://example.test/cached-error-token'])->toBe('Client error: `GET https://api.vimeo.com/videos/1?access_token=REDACTED&fields=created_time%2Cdescription` resulted in a `404 Not Found` response')
            ->and($stored['https://example.test/cached-error-token-last'])->toBe('Client error: `GET https://api.vimeo.com/videos/2?fields=name&access_token=REDACTED` resulted in a `403 Forbidden` response')
            ->and($stored['https://example.test/cached-error-plain'])->toBe('Unable to refresh video metadata.')
            ->and(implode(' ', $stored))->not->toContain($secret);
    } finally {
        VideoRecord::deleteAll(['videoUrl' => array_keys($rows)]);
    }
});
