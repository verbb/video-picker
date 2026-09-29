<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;

use Craft;
use craft\elements\User;
use craft\web\Controller;

use yii\web\Response;

use Throwable;

use verbb\auth\Auth;
use verbb\auth\helpers\Session;

class AuthController extends Controller
{
    // Properties
    // =========================================================================

    // Only the OAuth provider callback is anonymous — connect/disconnect require CP auth.
    protected array|int|bool $allowAnonymous = ['callback'];


    // Public Methods
    // =========================================================================

    public function beforeAction($action): bool
    {
        // Don't require CSRF validation for callback requests
        if ($action->id === 'callback') {
            $this->enableCsrfValidation = false;
        }

        return parent::beforeAction($action);
    }

    public function actionConnect(): ?Response
    {
        $this->requirePermission('videoPicker-sources');
        $this->requirePermission(VideoPicker::MANAGE_SOURCE_CREDENTIALS_PERMISSION);
        $this->requirePostRequest();

        $sourceHandle = $this->request->getRequiredParam('source');

        try {
            if (!($source = VideoPicker::$plugin->getSources()->getSourceByHandle($sourceHandle))) {
                return $this->asFailure(Craft::t('video-picker', 'Unable to find source “{source}”.', ['source' => $sourceHandle]));
            }

            if (!$source::supportsOAuthConnection()) {
                return $this->asFailure(Craft::t('video-picker', 'This source does not use OAuth connect.'));
            }

            $context = [
                'sourceHandle' => $sourceHandle,
            ];

            if ($this->request->getIsCpRequest()) {
                if ($redirect = $this->request->getValidatedBodyParam('redirect')) {
                    $context['redirect'] = $this->getView()->renderObjectTemplate($redirect, $source);
                }
            }

            return Auth::getInstance()->getOAuth()->connect('video-picker', $source, $source->id, $context);
        } catch (Throwable $e) {
            VideoPicker::error('Unable to authorize connect “{source}”: “{message}” {file}:{line}', [
                'source' => $sourceHandle,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return $this->asFailure(Craft::t('video-picker', 'Unable to authorize connect “{source}”.', ['source' => $sourceHandle]));
        }
    }

    public function actionCallback(): ?Response
    {
        $oauth = Auth::getInstance()->getOAuth();

        if ($response = $oauth->prepareCallback('video-picker')) {
            return $response;
        }

        $oauth->claimAuthorizedCallback(
            'video-picker',
            fn(User $user): bool => $user->can('videoPicker-sources') && $user->can(VideoPicker::MANAGE_SOURCE_CREDENTIALS_PERMISSION),
        );
        
        // Get both the origin (failure) and redirect (success) URLs
        $origin = Session::get('origin');
        $redirect = Session::get('redirect');

        // Get the source we're current authorizing
        if (!($sourceHandle = Session::get('sourceHandle'))) {
            Session::setError('video-picker', Craft::t('video-picker', 'Unable to find source.'), true);

            return $this->redirect($origin);
        }

        if (!($source = VideoPicker::$plugin->getSources()->getSourceByHandle($sourceHandle))) {
            Session::setError('video-picker', Craft::t('video-picker', 'Unable to find source “{source}”.', ['source' => $sourceHandle]), true);

            return $this->redirect($origin);
        }

        try {
            // Fetch the access token from the source and create a Token for us to use
            $token = $oauth->callback('video-picker', $source, $source->id);

            if (!$token) {
                Session::setError('video-picker', Craft::t('video-picker', 'Unable to fetch token.'), true);

                return $this->redirect($origin);
            }

            // Save the token to the Auth plugin, with a reference to this source
            $token->reference = $source->id;
            Auth::getInstance()->getTokens()->upsertToken($token);

            // Drop explorer sections + provider responses cached under a previous account/config.
            $source->clearExplorerCache();
        } catch (Throwable $e) {
            $error = Craft::t('video-picker', 'Unable to process callback for “{source}”: “{message}” {file}:{line}', [
                'source' => $sourceHandle,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            VideoPicker::error($error);

            // Show the error detail in the CP
            Craft::$app->getSession()->setFlash('video-picker:callback-error', $error);

            return $this->redirect($origin);
        }

        Session::setNotice('video-picker', Craft::t('video-picker', '{provider} connected.', ['provider' => $source->providerName]), true);

        return $this->redirect($redirect);
    }

    public function actionDisconnect(): ?Response
    {
        $this->requirePermission('videoPicker-sources');
        $this->requirePermission(VideoPicker::MANAGE_SOURCE_CREDENTIALS_PERMISSION);
        $this->requirePostRequest();

        $sourceHandle = $this->request->getRequiredParam('source');

        if (!($source = VideoPicker::$plugin->getSources()->getSourceByHandle($sourceHandle))) {
            return $this->asFailure(Craft::t('video-picker', 'Unable to find source “{source}”.', ['source' => $sourceHandle]));
        }

        if (!$source::supportsOAuthConnection()) {
            return $this->asFailure(Craft::t('video-picker', 'This source does not use OAuth connect.'));
        }

        // Delete all tokens for this source
        Auth::getInstance()->getTokens()->deleteTokenByOwnerReference('video-picker', $source->id);

        $source->clearExplorerCache();

        return $this->asModelSuccess($source, Craft::t('video-picker', '{provider} disconnected.', ['provider' => $source->providerName]), 'source');
    }

}
