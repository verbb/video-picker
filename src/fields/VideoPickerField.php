<?php
namespace verbb\videopicker\fields;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\gql\types\ArrayType;
use verbb\videopicker\helpers\Plugin;
use verbb\videopicker\helpers\Videos;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeLoader;
use craft\gql\types\DateTime;
use craft\helpers\ArrayHelper;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;

use yii\db\Schema;

use Throwable;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

class VideoPickerField extends Field
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('video-picker', 'Video Picker');
    }


    // Public Methods
    // =========================================================================

    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        $view = Craft::$app->getView();

        // We need at least some sources to continue
        if (!VideoPicker::$plugin->getSources()->getAllEnabledSources()) {
            return Html::tag('span', Craft::t('video-picker', 'Provide at least one enabled source to continue.'), ['class' => 'warning with-icon']);
        }

        $id = Html::id($this->handle);

        Plugin::registerAsset('field/src/js/video-picker.js');

        if ($value) {
            // Format the Video model for the front-end, and add any extra data
            $video = $value->getVideoData();
            $video['errors'] = $value->getErrors();
            $value = $video;
        }

        // Create the VideoPicker Input Vue component
        $js = 'new Craft.VideoPicker.Input(' . Json::encode([
            'inputId' => $view->namespaceInputId($id),
            'inputName' => $view->namespaceInputName($id),
            'fieldId' => $this->id,
            'value' => $value,
        ]) . ');';

        // Wait for VideoPicker JS to be loaded, either through an event listener, or by a flag.
        // This covers if this script is run before, or after the VideoPicker JS has loaded
        $view->registerJs('document.addEventListener("vite-script-loaded", function(e) {' .
            'if (e.detail.path === "field/src/js/video-picker.js") {' . $js . '}' .
        '}); if (Craft.VideoPickerReady) {' . $js . '}');

        return $view->renderTemplate('video-picker/_field/input');
    }

    public function getSettingsHtml(): ?string
    {
        return Craft::$app->getView()->renderTemplate('video-picker/_field/settings', [
            'field' => $this,
        ]);
    }

    public function normalizeValue(mixed $value, ?ElementInterface $element = null): ?Video
    {
        if ($value instanceof Video) {
            return $value;
        }

        if ($value && is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            $video = VideoPicker::$plugin->getVideos()->getVideoByUrl($value);

            if ($video) {
                return $video;
            }

            $video = new Video();
            $video->url = $value;
            $video->addError('url', Craft::t('video-picker', 'Unable to find the video.'));

            return $video;
        }

        return null;
    }

    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        if (!empty($value->url)) {
            return Db::prepareValueForDb($value->url);
        }

        return parent::serializeValue($value, $element);
    }

    public function getSearchKeywords(mixed $value, ElementInterface $element): string
    {
        $keywords = [];

        if ($value instanceof Video) {
            $keywords[] = $value->id;
            $keywords[] = $value->url;
            $keywords[] = $value->sourceHandle;
            $keywords[] = $value->authorName;
            $keywords[] = $value->authorUsername;
            $keywords[] = $value->title;
            $keywords[] = $value->description;
        }

        return StringHelper::encodeMb4(StringHelper::toString($keywords, ' '));
    }

    public function afterElementSave(ElementInterface $element, bool $isNew): void
    {
        $value = $element->getFieldValue($this->handle);

        if (!($value instanceof Video)) {
            return;
        }

        // Save or update our cached video data in a separate table
        VideoPicker::$plugin->getVideos()->saveVideo($value);
    }

    public function getContentGqlType(): array|Type
    {
        $typeName = $this->handle . '_Video';

        $videoType = GqlEntityRegistry::getEntity($typeName) ?: GqlEntityRegistry::createEntity($typeName, new ObjectType([
            'name' => $typeName,
            'fields' => [
                'id' => [
                    'name' => 'id',
                    'type' => Type::string(),
                    'description' => 'The id of the video.',
                ],
                'url' => [
                    'name' => 'url',
                    'type' => Type::string(),
                    'description' => 'The url of the video.',
                ],
                'sourceHandle' => [
                    'name' => 'sourceHandle',
                    'type' => Type::string(),
                    'description' => 'The source handle of the video.',
                ],
                'date' => [
                    'name' => 'date',
                    'type' => DateTime::getType(),
                    'description' => 'The published date of the video.',
                ],
                'duration' => [
                    'name' => 'duration',
                    'type' => Type::int(),
                    'description' => 'The duration (in seconds) of the video.',
                ],
                'formattedDuration' => [
                    'name' => 'formattedDuration',
                    'type' => Type::string(),
                    'description' => 'The formatted duration of the video.',
                ],
                'plays' => [
                    'name' => 'plays',
                    'type' => Type::int(),
                    'description' => 'The number of plays of the video.',
                ],
                'authorName' => [
                    'name' => 'authorName',
                    'type' => Type::string(),
                    'description' => 'The author name of the video.',
                ],
                'authorUrl' => [
                    'name' => 'authorUrl',
                    'type' => Type::string(),
                    'description' => 'The author url of the video.',
                ],
                'authorUsername' => [
                    'name' => 'authorUsername',
                    'type' => Type::string(),
                    'description' => 'The author username of the video.',
                ],
                'thumbnails' => [
                    'name' => 'thumbnails',
                    'type' => ArrayType::getType(),
                    'description' => 'The thumbnails of the video.',
                ],
                'title' => [
                    'name' => 'title',
                    'type' => Type::string(),
                    'description' => 'The title of the video.',
                ],
                'description' => [
                    'name' => 'description',
                    'type' => Type::string(),
                    'description' => 'The description of the video.',
                ],
                'private' => [
                    'name' => 'private',
                    'type' => Type::boolean(),
                    'description' => 'Whether the video is marked as private.',
                ],
                'width' => [
                    'name' => 'width',
                    'type' => Type::int(),
                    'description' => 'The width of the video.',
                ],
                'height' => [
                    'name' => 'height',
                    'type' => Type::int(),
                    'description' => 'The height of the video.',
                ],
                'raw' => [
                    'name' => 'raw',
                    'type' => Type::string(),
                    'description' => 'The raw data of the video as a JSON string.',
                     'resolve' => function($model) {
                        return Json::encode($model->raw);
                    },
                ],
                'embedHtml' => [
                    'name' => 'embedHtml',
                    'type' => Type::string(),
                    'description' => 'The embed HTML of the video.',
                ],
                'embedUrl' => [
                    'name' => 'embedUrl',
                    'type' => Type::string(),
                    'description' => 'The embed URL of the video.',
                ],
            ],
        ]));

        TypeLoader::registerType($typeName, static function() use ($videoType) {
            return $videoType;
        });

        return $videoType;
    }
}
