<?php
namespace verbb\videopicker\fields;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\gql\types\ArrayType;
use verbb\videopicker\helpers\Plugin;
use verbb\videopicker\models\Video;
use verbb\videopicker\records\Video as VideoRecord;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\base\ThumbableFieldInterface;
use craft\gql\GqlEntityRegistry;
use craft\gql\TypeLoader;
use craft\gql\types\DateTime;
use craft\helpers\ArrayHelper;
use craft\helpers\Cp;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;

use yii\db\Schema;
use yii\helpers\Markdown;

use Throwable;

use GraphQL\Type\Definition\Type;
use GraphQL\Type\Definition\ObjectType;

class VideoPickerField extends Field implements ThumbableFieldInterface, PreviewableFieldInterface
{
    // Static Methods
    // =========================================================================

    public static function displayName(): string
    {
        return Craft::t('video-picker', 'Video Picker');
    }

    public static function icon(): string
    {
        return '@verbb/videopicker/icon-mask.svg';
    }


    // Properties
    // =========================================================================

    public bool $showExplorer = true;
    public bool $showPreview = true;
    /** Shown in the empty URL control (e.g. “Enter a video URL”). */
    public ?string $placeholder = null;

    /** When false, editors must use Browse (no URL paste/typing). */
    public bool $allowUrlInput = true;

    /** When false, hide explorer search even if the provider supports it. */
    public bool $allowSearch = true;

    /** Hide private videos in the explorer and reject private URL resolves. */
    public bool $publicOnly = false;

    /** Optional per-field page size; null = plugin Videos Per Page. */
    public ?int $videosPerPage = null;

    /** Minimum duration in seconds (inclusive); null = no minimum. */
    public ?int $minDuration = null;

    /** Maximum duration in seconds (inclusive); null = no maximum. */
    public ?int $maxDuration = null;

    /**
     * Client-side sort of each explorer page after fetch.
     * Empty = provider order. Values: dateAsc, dateDesc, playsDesc, titleAsc.
     */
    public string $videoSort = '';


    // Public Methods
    // =========================================================================

    public function setVideosPerPage(mixed $value): void
    {
        $this->videosPerPage = ($value === '' || $value === null) ? null : (int)$value;
    }

    public function setMinDuration(mixed $value): void
    {
        $this->minDuration = ($value === '' || $value === null) ? null : (int)$value;
    }

    public function setMaxDuration(mixed $value): void
    {
        $this->maxDuration = ($value === '' || $value === null) ? null : (int)$value;
    }

    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        if (!$value) {
            return '';
        }

        $content = array_filter([
            $value->getSource()->name ?? '',
            $value->url,
        ]);

        return implode(' - ', $content);
    }

    public function getThumbHtml(mixed $value, ElementInterface $element, int $size): ?string
    {
        if (!$value) {
            return '';
        }

        $videoData = $value->getVideoData();

        $thumbUrl = $videoData['thumbnail'] ?? '';

        if (!$thumbUrl) {
            return '';
        } 

        return Html::tag('div', '', [
            'class' => 'thumb',
            'data' => [
                'sizes' => sprintf('calc(%srem/16)', $size),
                // Single candidate — we only have one thumbnail URL (was emitting an empty 2x).
                'srcset' => sprintf('%s %sw', $thumbUrl, $size),
            ],
        ]);
    }

    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        $view = Craft::$app->getView();

        Plugin::registerFieldAssets();

        if ($value) {
            // Format the Video model for the front-end, and add any extra data
            $video = $value->getVideoData();
            $video['errors'] = $value->getErrors();
            $value = $video;
        }

        // Twig gets the raw handle — CustomField wraps getInputHtml in
        // namespaceInputs(..., 'fields'). Pre-namespacing here double-wraps to
        // fields[fields][handle] (draft dirty-checks, value never applies).
        // JS fallback create (no SSR) still needs the fully namespaced name.
        $inputName = $view->namespaceInputName($this->handle);
        $urlValue = is_array($value) ? (string)($value['url'] ?? '') : '';

        $fieldSources = VideoPicker::$plugin->getSources()->getSourcesForField($this);
        $allowedSources = VideoPicker::$plugin->getSources()->getSourcesForField($this, false);
        $sourceCount = count($fieldSources);
        $hasGlobalSources = count(VideoPicker::$plugin->getSources()->getAllEnabledSources()) > 0;

        // Distinguish no plugin sources / Available Fields / not connected yet.
        if (!$hasGlobalSources) {
            $sourceWarning = Craft::t('video-picker', 'Provide at least one enabled [source]({link}) to browse videos and fetch video data.', [
                'link' => UrlHelper::cpUrl('video-picker/sources'),
            ]);
        } elseif (count($allowedSources) === 0) {
            $sourceWarning = Craft::t('video-picker', 'No sources are available for this field. On each source, set Available Fields to All (or include this field).');
        } elseif ($sourceCount === 0) {
            $sourceWarning = Craft::t('video-picker', 'No connected sources are available for this field. [Connect a source]({link}) that allows this field.', [
                'link' => UrlHelper::cpUrl('video-picker/sources'),
            ]);
        } else {
            $sourceWarning = '';
        }

        // Control id: unsuffixed handle id. CustomField’s namespaceInputs rewrites
        // label[for]; JS syncs pk-input to that for (see associateCraftFieldLabel).
        $componentSettings = [
            'inputId' => $this->getInputId(),
            'inputName' => $inputName,
            'fieldId' => $this->id,
            'value' => $value,
            'showExplorer' => $this->showExplorer,
            'showPreview' => $this->showPreview,
            'allowUrlInput' => $this->allowUrlInput,
            'allowSearch' => $this->allowSearch,
            'placeholder' => $this->placeholder,
            'sourceCount' => $sourceCount,
            'sourceWarning' => $sourceWarning ? Markdown::processParagraph($sourceWarning) : '',
        ];

        return $view->renderTemplate('video-picker/_field/input', [
            'name' => $this->handle,
            'value' => $urlValue,
            'componentSettings' => Json::encode($componentSettings, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]);
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

        if ($value && is_string($value) && filter_var(trim($value), FILTER_VALIDATE_URL)) {
            // Only resolve against sources that allow this field (source Available Fields).
            $video = VideoPicker::$plugin->getVideos()->getVideoByUrl($value, false, $this);

            if ($video) {
                if ($reason = $this->selectionPolicyError($video)) {
                    $video->addError('url', $reason);
                }

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

    /**
     * Effective explorer/API page size for this field.
     */
    public function resolveVideosPerPage(): int
    {
        if ($this->videosPerPage !== null) {
            return max(1, min(50, $this->videosPerPage));
        }

        $settings = VideoPicker::$plugin->getSettings();

        return max(1, min(50, (int)$settings->videosPerPage));
    }

    /**
     * @return string[] Craft select options for videoSort.
     */
    public static function videoSortOptions(): array
    {
        return [
            '' => Craft::t('video-picker', 'Provider order'),
            'dateDesc' => Craft::t('video-picker', 'Newest first'),
            'dateAsc' => Craft::t('video-picker', 'Oldest first'),
            'playsDesc' => Craft::t('video-picker', 'Most plays'),
            'titleAsc' => Craft::t('video-picker', 'Title A–Z'),
        ];
    }

    /**
     * Whether a resolved video may be selected under this field’s policy.
     */
    public function selectionPolicyError(?Video $video): ?string
    {
        if (!$video || $video->hasErrors()) {
            return null;
        }

        if ($this->publicOnly && $video->private) {
            return Craft::t('video-picker', 'Private videos are not allowed for this field.');
        }

        $duration = $video->duration;

        if ($duration !== null) {
            if ($this->minDuration !== null && $duration < $this->minDuration) {
                return Craft::t('video-picker', 'This video is shorter than the minimum duration for this field.');
            }

            if ($this->maxDuration !== null && $duration > $this->maxDuration) {
                return Craft::t('video-picker', 'This video is longer than the maximum duration for this field.');
            }
        }

        return null;
    }

    /**
     * Filter + sort Video models for explorer pages (best-effort on the current page).
     *
     * @param Video[] $videos
     * @return Video[]
     */
    public function applyExplorerVideoPolicy(array $videos): array
    {
        $filtered = [];

        foreach ($videos as $video) {
            if (!$video instanceof Video) {
                continue;
            }

            if ($this->selectionPolicyError($video)) {
                continue;
            }

            $filtered[] = $video;
        }

        return $this->sortVideos($filtered);
    }

    /**
     * @param Video[] $videos
     * @return Video[]
     */
    public function sortVideos(array $videos): array
    {
        $sort = $this->videoSort;

        if ($sort === '' || !isset(self::videoSortOptions()[$sort])) {
            return array_values($videos);
        }

        usort($videos, static function(Video $a, Video $b) use ($sort): int {
            return match ($sort) {
                'dateAsc' => ($a->date?->getTimestamp() ?? 0) <=> ($b->date?->getTimestamp() ?? 0),
                'dateDesc' => ($b->date?->getTimestamp() ?? 0) <=> ($a->date?->getTimestamp() ?? 0),
                'playsDesc' => ($b->plays ?? 0) <=> ($a->plays ?? 0),
                'titleAsc' => strcasecmp((string)$a->title, (string)$b->title),
                default => 0,
            };
        });

        return array_values($videos);
    }

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [[
            'showExplorer',
            'showPreview',
            'allowUrlInput',
            'allowSearch',
            'publicOnly',
        ], 'boolean'];
        $rules[] = [['videosPerPage'], 'number', 'integerOnly' => true, 'min' => 1, 'max' => 50, 'skipOnEmpty' => true];
        $rules[] = [['minDuration', 'maxDuration'], 'number', 'integerOnly' => true, 'min' => 0, 'skipOnEmpty' => true];
        $rules[] = [['videoSort'], 'in', 'range' => array_keys(self::videoSortOptions())];
        $rules[] = [['allowUrlInput'], 'validateSelectionMode'];

        return $rules;
    }

    public function validateSelectionMode(string $attribute): void
    {
        if (!$this->allowUrlInput && !$this->showExplorer) {
            $this->addError($attribute, Craft::t('video-picker', 'Enable URL input and/or Show Explorer so editors can select a video.'));
        }

        if (
            $this->minDuration !== null
            && $this->maxDuration !== null
            && $this->minDuration > $this->maxDuration
        ) {
            $this->addError('minDuration', Craft::t('video-picker', 'Minimum duration cannot be greater than maximum duration.'));
        }
    }

}
