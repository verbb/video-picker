<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\helpers\Videos;

use Craft;
use craft\helpers\ArrayHelper;
use craft\helpers\Json;
use craft\web\Controller;

use Throwable;

use yii\web\BadRequestHttpException;
use yii\web\NotFoundHttpException;
use yii\web\Response;

class VideosController extends Controller
{
    // Public Methods
    // =========================================================================

    public function actionGetSources(): Response
    {
        $refresh = (bool)$this->request->getParam('refresh');
        $sources = VideoPicker::$plugin->getSources()->getAllEnabledSources();

        $data = [];

        foreach ($sources as $source) {
            $data[] = [
                'name' => $source->name,
                'handle' => $source->handle,
                'sections' => $source->getExplorerSections($refresh),
            ];
        }

        return $this->asJson($data);
    }

    public function actionGetVideos(): Response
    {
        $this->requireAcceptsJson();

        $sourceHandle = $this->request->getRequiredParam('source');
        $method = $this->request->getRequiredParam('method');
        $options = $this->request->getParam('options') ?? [];

        $source = VideoPicker::$plugin->getSources()->getSourceByHandle($sourceHandle);

        $videosResponse = $source->getVideos($method, $options);

        $videos = [];

        foreach ($videosResponse['videos'] as $video) {
            $videos[] = $video->getVideoData();
        }

        return $this->asJson([
            'videos' => $videos,
            'nextPage' => $videosResponse['nextPage'],
        ]);
    }

    public function actionGetVideo(): Response
    {
        $this->requireAcceptsJson();

        $url = $this->request->getRequiredParam('url');
        $refresh = (bool)$this->request->getParam('refresh');
        $video = VideoPicker::getInstance()->getVideos()->getVideoByUrl($url, $refresh);

        if (!$video) {
            return $this->asErrorJson(Craft::t('video-picker', 'Unable to find the video.'));
        }

        return $this->asJson($video->getVideoData());
    }
}
