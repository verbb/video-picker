<?php

declare(strict_types=1);

use craft\helpers\Db;
use verbb\videopicker\migrations\m260822_120000_video_cache_meta;
use verbb\videopicker\records\Video as VideoRecord;

it('backfills cache dates when retrying the metadata migration', function() {
    $url = 'https://example.test/video-cache-meta-migration';
    $dateUpdated = '2025-07-10 01:05:18';

    Craft::$app->getDb()->createCommand()->insert('{{%video_picker_videos}}', [
        'videoId' => 'video-cache-meta-migration',
        'videoUrl' => $url,
        'data' => null,
        'dataVersion' => 0,
        'fetchedAt' => null,
        'expiresAt' => null,
        'status' => 'ok',
        'lastError' => null,
        'dateCreated' => $dateUpdated,
        'dateUpdated' => $dateUpdated,
        'uid' => '9ed837d5-397a-4b1d-bb0c-3cd0ce6ef39c',
    ])->execute();

    try {
        $migration = new m260822_120000_video_cache_meta();

        // Existing columns and indexes reproduce a retry after MySQL retained the DDL.
        expect($migration->safeUp())->toBeTrue();

        $record = VideoRecord::findOne(['videoUrl' => $url]);
        expect($record->fetchedAt)->toBe(Db::prepareDateForDb($dateUpdated))
            ->and($record->expiresAt)->toBe('2025-07-17 01:05:18')
            ->and($record->status)->toBe('ok')
            ->and($record->lastError)->toBeNull();
    } finally {
        VideoRecord::deleteAll(['videoUrl' => $url]);
    }
});
