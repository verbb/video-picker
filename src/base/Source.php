<?php
namespace verbb\videopicker\base;

use verbb\videopicker\VideoPicker;
use verbb\videopicker\models\Video;

use Craft;
use craft\base\SavableComponent;
use craft\helpers\Db;
use craft\helpers\Html;
use craft\helpers\Json;
use craft\helpers\StringHelper;
use craft\helpers\UrlHelper;
use craft\validators\HandleValidator;

use verbb\auth\helpers\Provider as ProviderHelper;

use DateTime;
use Exception;
use Throwable;

use GuzzleHttp\Exception\RequestException;

abstract class Source extends SavableComponent implements SourceInterface
{
    // Static Methods
    // =========================================================================

    public static function apiError($source, $exception, $throwError = true): void
    {
        $messageText = $exception->getMessage();

        // Check for Guzzle errors, which are truncated in the exception `getMessage()`.
        if ($exception instanceof RequestException && $exception->getResponse()) {
            $messageText = (string)$exception->getResponse()->getBody();
        }

        $message = Craft::t('video-picker', 'API error: “{message}” {file}:{line}', [
            'message' => $messageText,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        VideoPicker::error($source->name . ': ' . $message);

        if ($throwError) {
            throw new Exception($message);
        }
    }

    public static function supportsSearch(): bool
    {
        return true;
    }


    // Properties
    // =========================================================================

    public ?string $name = null;
    public ?string $handle = null;
    public ?bool $enabled = null;
    public ?int $sortOrder = null;
    public array $cache = [];
    public ?string $uid = null;

    // Set via config files
    public array $authorizationOptions = [];
    public array $scopes = [];


    // Abstract Methods
    // =========================================================================

    abstract public static function getOAuthProviderClass(): string;


    // Public Methods
    // =========================================================================

    public function defineRules(): array
    {
        $rules = parent::defineRules();

        $rules[] = [['name', 'handle'], 'required'];
        $rules[] = [['id'], 'number', 'integerOnly' => true];

        $rules[] = [
            ['handle'],
            HandleValidator::class,
            'reservedWords' => [
                'dateCreated',
                'dateUpdated',
                'edit',
                'id',
                'title',
                'uid',
            ],
        ];

        return $rules;
    }

    public function getProviderName(): string
    {
        return static::displayName();
    }

    public function getProviderHandle(): string
    {
        return static::$providerHandle;
    }

    public function getPrimaryColor(): ?string
    {
        return ProviderHelper::getPrimaryColor(static::$providerHandle);
    }

    public function getIcon(): ?string
    {
        return ProviderHelper::getIcon(static::$providerHandle);
    }

    public function isConnected(): bool
    {
        return false;
    }

    public function getSettingsHtml(): ?string
    {
        $handle = StringHelper::toKebabCase(static::$providerHandle);

        return Craft::$app->getView()->renderTemplate('video-picker/sources/_types/' . $handle . '/settings', [
            'source' => $this,
        ]);
    }

    public function getExplorerSections(bool $clearCache = false): array
    {
        if ($clearCache) {
            $this->cache = [];
        }

        // Use the cache of explorer data, if available
        if ($this->cache) {
            return $this->cache;
        }

        $this->cache = $this->fetchExplorerSections();

        // Direct DB update to keep it out of PC, plus speed
        Db::update('{{%video_picker_sources}}', ['cache' => Json::encode($this->cache)], ['id' => $this->id]);

        return $this->cache;
    }

    public function getVideos(string $method, array $options = []): array
    {
        $methodName = 'fetchVideos' . ucwords($method);

        if (method_exists($this, $methodName)) {
            return $this->{$methodName}($options);
        }

        return [];
    }

    public function getVideoById(string $id): ?Video
    {
        return null;
    }

    public function getVideoByUrl(string $url): ?Video
    {
        try {
            if ($videoId = $this->getVideoIdFromUrl($url)) {
                return $this->getVideoById($videoId);
            }
        } catch (Throwable $e) {
            $video = new Video();
            $video->url = $url;
            $video->addError('url', $e->getMessage());

            return $video; 
        }

        return null;
    }

    public function getVideoIdFromUrl(string $url): ?string
    {
        return null;
    }

    public function getVideosPerPage(): int
    {
        return VideoPicker::$plugin->getSettings()->videosPerPage;
    }

    public function getEmbedUrlFormat(): string
    {
        return '';
    }

    public function getEmbedUrl(string $videoId, array $options = []): string
    {
        $url = Craft::t('app', $this->getEmbedUrlFormat(), ['id' => $videoId]);

        if ($options) {
            // Add any options to the URL as a query string
            $url = UrlHelper::urlWithParams($url, $options);
        }

        return $url;
    }

    public function getEmbedHtml(string $videoId, array $options = []): string
    {
        $attributes = array_replace([
            'src' => $this->getEmbedUrl($videoId, $options),
            'title' => 'External video from ' . $this->handle,
            'frameborder' => '0',
            'allowfullscreen' => 'true',
            'allowscriptaccess' => 'true',
            'allow' => 'autoplay; encrypted-media',
        ], $options);

        return Html::tag('iframe', null, $attributes);
    }


    // Protected Methods
    // =========================================================================

    protected function fetchExplorerSections(): array
    {
        return [];
    }
}
