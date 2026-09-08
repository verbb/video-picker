/**
 * Seed Video Picker field, all supported provider sources (connected), and an entry with a resolved video.
 *
 * Echoes JSON: fieldId, fieldHandle, settingsRoute, entryEditRoute, sourcesRoute.
 * Note: no opening PHP tag — @verbb/docs-screenshots injects this into a bootstrap.
 *
 * Requires modules/videopickerdocs (DocsVimeoSource) bootstrapped in the Craft install.
 */

use craft\elements\Entry;
use craft\fieldlayoutelements\CustomField;
use craft\helpers\Json;
use craft\models\EntryType;
use craft\models\FieldLayout;
use craft\models\FieldLayoutTab;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\Site;
use modules\videopickerdocs\DocsVimeoSource;

use verbb\auth\Auth;
use verbb\auth\models\Token;
use verbb\videopicker\VideoPicker;
use verbb\videopicker\base\Source;
use verbb\videopicker\base\SourceInterface;
use verbb\videopicker\fields\VideoPickerField;
use verbb\videopicker\sources\BunnyStream;
use verbb\videopicker\sources\CloudflareStream;
use verbb\videopicker\sources\Dailymotion;
use verbb\videopicker\sources\Mux;
use verbb\videopicker\sources\SproutVideo;
use verbb\videopicker\sources\Wistia;
use verbb\videopicker\sources\YouTube;

const DOCS_FIELD_HANDLE = 'docsScreenshotVideoPicker';
const DOCS_SECTION_HANDLE = 'docsScreenshotVideoPicker';
const DOCS_ENTRY_HANDLE = 'docs-screenshot-entry';
const DOCS_VIDEO_URL = 'https://vimeo.com/1025521358';
const DOCS_SCREENSHOT_CREDENTIAL = 'docs-screenshot-placeholder';

function docsScreenshotSite(): Site
{
    return Craft::$app->getSites()->getPrimarySite();
}

function docsScreenshotAdminPath(): string
{
    return Craft::$app->getConfig()->getGeneral()->cpTrigger ?: 'admin';
}

function docsScreenshotSection(string $handle, string $name): Section
{
    $entriesService = Craft::$app->getEntries();
    $section = $entriesService->getSectionByHandle($handle);
    $site = docsScreenshotSite();

    if (!$section) {
        $entryType = new EntryType([
            'name' => $name,
            'handle' => $handle . 'Type',
            'hasTitleField' => true,
        ]);

        if (!$entriesService->saveEntryType($entryType)) {
            throw new RuntimeException('Unable to save entry type: ' . Json::encode($entryType->getErrors()));
        }

        $section = new Section([
            'name' => $name,
            'handle' => $handle,
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

        if (!$entriesService->saveSection($section)) {
            throw new RuntimeException('Unable to save section: ' . Json::encode($section->getErrors()));
        }

        $section = $entriesService->getSectionByHandle($handle);
    }

    if (!$section) {
        throw new RuntimeException("Section `{$handle}` could not be reloaded.");
    }

    return $section;
}

function docsScreenshotUpsertSource(
    string $type,
    string $name,
    string $handle,
    array $extra = [],
    ?int $sortOrder = null,
): SourceInterface {
    $sources = VideoPicker::$plugin->getSources();
    $existing = $sources->getSourceByHandle($handle);

    $source = $existing ?: $sources->createSource(array_merge([
        'type' => $type,
        'handle' => $handle,
    ], $extra));

    $source->name = $name;
    $source->enabled = true;

    if ($sortOrder !== null) {
        $source->sortOrder = $sortOrder;
    }

    foreach ($extra as $key => $value) {
        if (property_exists($source, $key)) {
            $source->$key = $value;
        }
    }

    if (!$sources->saveSource($source)) {
        throw new RuntimeException("Unable to save source {$handle}: " . Json::encode($source->getErrors()));
    }

    $saved = $sources->getSourceByHandle($handle);

    if (!$saved) {
        throw new RuntimeException("Source `{$handle}` could not be reloaded.");
    }

    return $saved;
}

function docsScreenshotRemoveSource(string $handle): void
{
    $sources = VideoPicker::$plugin->getSources();
    $existing = $sources->getSourceByHandle($handle);

    if ($existing) {
        $sources->deleteSource($existing);
    }
}

/** Mark credentials sources connected without hitting remote APIs. */
function docsScreenshotMarkCredentialsConnected(SourceInterface $source): void
{
    if (!$source->supportsConnection() || $source::supportsOAuthConnection()) {
        return;
    }

    if (!$source->id) {
        throw new RuntimeException('Cannot mark source connected before it is saved.');
    }

    // setConnectionCache persists to the sources.cache column; saveSource() does not.
    $method = new ReflectionMethod(Source::class, 'setConnectionCache');
    $method->setAccessible(true);
    $method->invoke($source, Source::CONNECT_SUCCESS);
}

function docsScreenshotSaveOAuthToken(SourceInterface $source): void
{
    if (!$source->id || !$source::supportsOAuthConnection()) {
        return;
    }

    if (!$source instanceof \verbb\videopicker\base\OAuthSource) {
        return;
    }

    $token = new Token([
        'ownerHandle' => 'video-picker',
        'providerType' => get_class($source->getOAuthProvider()),
        'reference' => (string)$source->id,
        'tokenType' => Token::TOKEN_TYPE_OAUTH2,
        'accessToken' => 'docs-screenshot-access-token',
        'refreshToken' => 'docs-screenshot-refresh-token',
        'expires' => (string)(time() + 86400 * 365),
    ]);

    if (!Auth::getInstance()->getTokens()->saveToken($token)) {
        throw new RuntimeException('Unable to save docs OAuth token for source ' . $source->handle);
    }
}

function docsScreenshotConnectSource(SourceInterface $source): void
{
    $sources = VideoPicker::$plugin->getSources();
    $source = $sources->getSourceByHandle($source->handle) ?? $source;

    if ($source::supportsOAuthConnection()) {
        docsScreenshotSaveOAuthToken($source);

        return;
    }

    if ($source instanceof DocsVimeoSource) {
        $source->checkConnection(false);

        return;
    }

    docsScreenshotMarkCredentialsConnected($source);
}

function docsScreenshotSeedSourcesIndex(): void
{
    // Drop legacy docs-prefixed handles so re-seeds do not leave duplicate rows.
    foreach ([
        'docsDemoVideos',
        'docsBunnyStream',
        'docsCloudflareStream',
        'docsDailymotion',
        'docsMux',
        'docsSproutVideo',
        'docsVimeo',
        'docsWistia',
        'docsYouTube',
    ] as $legacyHandle) {
        docsScreenshotRemoveSource($legacyHandle);
    }

    $credential = DOCS_SCREENSHOT_CREDENTIAL;
    $rows = [
        [BunnyStream::class, 'Bunny Stream', 'bunnyStream', [
            'libraryId' => $credential,
            'streamApiKey' => $credential,
        ], 1],
        [CloudflareStream::class, 'Cloudflare Stream', 'cloudflareStream', [
            'accountId' => $credential,
            'apiToken' => $credential,
        ], 2],
        [Dailymotion::class, 'Dailymotion', 'dailymotion', [
            'apiKey' => $credential,
            'apiSecret' => $credential,
        ], 3],
        [Mux::class, 'Mux', 'mux', [
            'tokenId' => $credential,
            'tokenSecret' => $credential,
        ], 4],
        [SproutVideo::class, 'Sprout Video', 'sproutVideo', [
            'apiKey' => $credential,
        ], 5],
        [DocsVimeoSource::class, 'Vimeo', 'vimeo', [], 6],
        [Wistia::class, 'Wistia', 'wistia', [
            'accessToken' => $credential,
        ], 7],
        [YouTube::class, 'YouTube', 'youTube', [
            'clientId' => $credential,
            'clientSecret' => $credential,
        ], 8],
    ];

    foreach ($rows as [$type, $name, $handle, $extra, $sortOrder]) {
        $source = docsScreenshotUpsertSource($type, $name, $handle, $extra, $sortOrder);
        docsScreenshotConnectSource($source);
    }
}

function docsScreenshotVideoPickerField(): VideoPickerField
{
    $fields = Craft::$app->getFields();
    $existing = $fields->getFieldByHandle(DOCS_FIELD_HANDLE);

    $field = $existing instanceof VideoPickerField ? $existing : new VideoPickerField([
        'handle' => DOCS_FIELD_HANDLE,
    ]);

    $field->name = 'Video Picker';
    $field->instructions = 'Pick a video from a connected source.';
    $field->showExplorer = true;
    $field->showPreview = true;
    $field->allowUrlInput = true;

    if (!$fields->saveField($field)) {
        throw new RuntimeException('Unable to save Video Picker field: ' . Json::encode($field->getErrors()));
    }

    $saved = $fields->getFieldByHandle(DOCS_FIELD_HANDLE);

    if (!$saved instanceof VideoPickerField) {
        throw new RuntimeException('Video Picker field could not be reloaded.');
    }

    return $saved;
}

function docsScreenshotAttachField(Section $section, VideoPickerField $field): void
{
    $entriesService = Craft::$app->getEntries();
    $entryType = $entriesService->getEntryTypesBySectionId($section->id)[0] ?? null;

    if (!$entryType) {
        throw new RuntimeException("Section `{$section->handle}` has no entry types.");
    }

    $layout = $entryType->getFieldLayout() ?? new FieldLayout(['type' => Entry::class]);
    $tabs = $layout->getTabs();

    if (!$tabs) {
        $tabs = [new FieldLayoutTab(['name' => Craft::t('app', 'Content'), 'layout' => $layout])];
    }

    $tab = $tabs[0];
    $elements = array_values(array_filter(
        $tab->getElements(),
        static fn($element) => !($element instanceof CustomField && $element->getField()?->id === $field->id),
    ));
    $elements[] = new CustomField($field);
    $tab->setElements($elements);
    $layout->setTabs($tabs);
    $entryType->setFieldLayout($layout);

    if (!$entriesService->saveEntryType($entryType)) {
        throw new RuntimeException('Unable to attach Video Picker field: ' . Json::encode($entryType->getErrors()));
    }
}

function docsScreenshotUpsertEntry(Section $section, string $slug, string $title, string $fieldHandle, string $videoUrl): Entry
{
    $site = docsScreenshotSite();
    $entryType = Craft::$app->getEntries()->getEntryTypesBySectionId($section->id)[0] ?? null;

    if (!$entryType) {
        throw new RuntimeException("Section `{$section->handle}` has no entry types.");
    }

    $entry = Entry::find()->sectionId($section->id)->slug($slug)->siteId($site->id)->status(null)->one();

    if (!$entry) {
        $entry = new Entry([
            'sectionId' => $section->id,
            'typeId' => $entryType->id,
            'siteId' => $site->id,
            'slug' => $slug,
            'enabled' => true,
        ]);
    }

    $entry->title = $title;
    $entry->enabled = true;
    $entry->setFieldValue($fieldHandle, $videoUrl);

    if (!Craft::$app->getElements()->saveElement($entry)) {
        throw new RuntimeException('Unable to save entry: ' . Json::encode($entry->getErrors()));
    }

    return $entry;
}

$adminPath = docsScreenshotAdminPath();

// Full provider list for the Sources index — all connected, no demo row.
docsScreenshotSeedSourcesIndex();

$section = docsScreenshotSection(DOCS_SECTION_HANDLE, 'Video Picker Demo');
$field = docsScreenshotVideoPickerField();
docsScreenshotAttachField($section, $field);

// Resolve + cache via Demo source so entry edit shows preview without live OAuth.
$entry = docsScreenshotUpsertEntry($section, DOCS_ENTRY_HANDLE, 'Demo', $field->handle, DOCS_VIDEO_URL);
VideoPicker::$plugin->getVideos()->getVideoByUrl(DOCS_VIDEO_URL, true, $field);

$entryEditUrl = $entry->getCpEditUrl();
$entryEditPath = parse_url((string)$entryEditUrl, PHP_URL_PATH)
    ?: "/{$adminPath}/entries/{$section->handle}/{$entry->id}-{$entry->slug}";

echo Json::encode([
    'fieldId' => (int)$field->id,
    'fieldHandle' => $field->handle,
    'settingsRoute' => "/{$adminPath}/settings/fields/edit/{$field->id}",
    'entryEditRoute' => $entryEditPath,
    'sourcesRoute' => "/{$adminPath}/video-picker/sources",
], JSON_THROW_ON_ERROR);
