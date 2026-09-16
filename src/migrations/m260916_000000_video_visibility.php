<?php
namespace verbb\videopicker\migrations;

use verbb\videopicker\sources\CloudflareStream;
use verbb\videopicker\sources\Mux;

use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

class m260916_000000_video_visibility extends Migration
{
    // Public Methods
    // =========================================================================

    public function safeUp(): bool
    {
        $types = (new Query())->select(['type'])->from('{{%video_picker_sources}}')->indexBy('handle')->column();

        foreach ((new Query())->select(['id', 'data'])->from('{{%video_picker_videos}}')->each() as $row) {
            $data = Json::decode($row['data']);
            $type = $types[$data['sourceHandle'] ?? ''] ?? null;
            $raw = $data['raw'] ?? [];

            if ($type === CloudflareStream::class) {
                $data['private'] = (bool)($raw['requireSignedURLs'] ?? false);
            } elseif ($type === Mux::class) {
                $data['private'] = true;

                foreach (($raw['playback_ids'] ?? []) as $playback) {
                    if (($playback['id'] ?? null) === ($data['id'] ?? null)) {
                        $data['private'] = ($playback['policy'] ?? '') !== 'public';
                        break;
                    }
                }
            } else {
                continue;
            }

            $this->update('{{%video_picker_videos}}', ['data' => Json::encode($data)], ['id' => $row['id']], [], false);
        }

        return true;
    }
}
