/** Seed real Video Picker sources, a field and an entry for Craft 5 captures. */

use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\Json;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use modules\videopickerscreenshots\ScreenshotVimeoSource;
use modules\videopickerscreenshots\ScreenshotYouTubeSource;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\fields\VideoPickerField;

const SCREENSHOT_FIELD_HANDLE = 'screenshotVideo';
const SCREENSHOT_SECTION_HANDLE = 'videoPickerScreenshots';
const SCREENSHOT_VIDEO_URL = 'https://vimeo.com/1025521358';

$sources = VideoPicker::$plugin->getSources();
$sourceDefinitions = [
    [ScreenshotVimeoSource::class, 'Vimeo Library', 'vimeo'],
    [ScreenshotYouTubeSource::class, 'YouTube Channel', 'youTube'],
    [ScreenshotYouTubeSource::class, 'YouTube Tutorials', 'youTubeTutorials'],
];

foreach ($sourceDefinitions as [$type, $name, $handle]) {
    $source = $sources->getSourceByHandle($handle) ?? $sources->createSource([
        'type' => $type,
        'handle' => $handle,
    ]);
    $source->name = $name;
    $source->enabled = true;

    if (!$sources->saveSource($source)) {
        throw new RuntimeException("Unable to save Video Picker source {$handle}: " . Json::encode($source->getErrors()));
    }
}

$fields = Craft::$app->getFields();
$field = $fields->getFieldByHandle(SCREENSHOT_FIELD_HANDLE);

if (!$field instanceof VideoPickerField) {
    $field = new VideoPickerField(['handle' => SCREENSHOT_FIELD_HANDLE]);
}

$field->name = 'Featured video';
$field->instructions = 'Choose a video from a connected source or paste its URL.';
$field->showExplorer = true;
$field->showPreview = true;

if (!$fields->saveField($field)) {
    throw new RuntimeException('Unable to save the Video Picker field: ' . Json::encode($field->getErrors()));
}

$entries = Craft::$app->getEntries();
$section = $entries->getSectionByHandle(SCREENSHOT_SECTION_HANDLE);
$site = Craft::$app->getSites()->getPrimarySite();

if (!$section) {
    $entryType = new EntryType([
        'name' => 'Video article',
        'handle' => 'videoArticle',
        'hasTitleField' => true,
    ]);

    if (!$entries->saveEntryType($entryType)) {
        throw new RuntimeException('Unable to save the entry type: ' . Json::encode($entryType->getErrors()));
    }

    $section = new Section([
        'name' => 'Video articles',
        'handle' => SCREENSHOT_SECTION_HANDLE,
        'type' => Section::TYPE_CHANNEL,
    ]);
    $section->setEntryTypes([$entryType]);
    $section->setSiteSettings([
        new Section_SiteSettings([
            'siteId' => $site->id,
            'enabledByDefault' => true,
            'hasUrls' => false,
        ]),
    ]);

    if (!$entries->saveSection($section)) {
        throw new RuntimeException('Unable to save the section: ' . Json::encode($section->getErrors()));
    }

    $section = $entries->getSectionByHandle(SCREENSHOT_SECTION_HANDLE);
}

$entryType = $entries->getEntryTypesBySectionId($section->id)[0] ?? null;

if (!$entryType) {
    throw new RuntimeException('The Video Picker screenshot section has no entry type.');
}

$layout = $entryType->getFieldLayout() ?? new FieldLayout(['type' => Entry::class]);
$tab = $layout->getTabs()[0] ?? new FieldLayoutTab(['name' => 'Content', 'layout' => $layout]);
$elements = array_values(array_filter(
    $tab->getElements(),
    static fn($element) => !($element instanceof CustomField && $element->getField()?->id === $field->id),
));
$elements[] = new CustomField($field);
$tab->setElements($elements);
$layout->setTabs([$tab]);
$entryType->setFieldLayout($layout);

if (!$entries->saveEntryType($entryType)) {
    throw new RuntimeException('Unable to attach the Video Picker field: ' . Json::encode($entryType->getErrors()));
}

$entry = Entry::find()->sectionId($section->id)->siteId($site->id)->status(null)->one() ?? new Entry([
    'sectionId' => $section->id,
    'typeId' => $entryType->id,
    'siteId' => $site->id,
    'enabled' => true,
]);
$entry->title = 'Ocean stories';
$entry->slug = 'ocean-stories';
$entry->enabled = true;
$entry->setFieldValue($field->handle, SCREENSHOT_VIDEO_URL);

if (!Craft::$app->getElements()->saveElement($entry)) {
    throw new RuntimeException('Unable to save the Video Picker entry: ' . Json::encode($entry->getErrors()));
}

$adminPath = Craft::$app->getConfig()->getGeneral()->cpTrigger ?: 'admin';
$entryEditRoute = parse_url((string)$entry->getCpEditUrl(), PHP_URL_PATH)
    ?: "/{$adminPath}/entries/{$section->handle}/{$entry->id}-{$entry->slug}";

echo Json::encode([
    'entryEditRoute' => $entryEditRoute,
    'sourcesRoute' => "/{$adminPath}/video-picker/sources",
], JSON_THROW_ON_ERROR);
