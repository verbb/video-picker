<?php
namespace verbb\videopicker\controllers;

use verbb\videopicker\VideoPicker;

use Craft;
use craft\web\Controller;

use yii\web\Response;

use verbb\auth\Auth;
use verbb\auth\helpers\Session;

use Throwable;

class AuthController extends Controller
{
    // Properties
    // =========================================================================

    protected array|int|bool $allowAnonymous = ['connect', 'callback'];


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
        $sourceHandle = $this->request->getRequiredParam('source');

        try {
            if (!($source = VideoPicker::$plugin->getSources()->getSourceByHandle($sourceHandle))) {
                return $this->asFailure(Craft::t('video-picker', 'Unable to find source “{source}”.', ['source' => $sourceHandle]));
            }

            // Keep track of which source instance is for, so we can fetch it in the callback
            Session::set('sourceHandle', $sourceHandle);

            return Auth::$plugin->getOAuth()->connect('video-picker', $source);
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
        // Restore the session data that we saved before authorization redirection from the cache back to session
        Session::restoreSession($this->request->getParam('state'));
        
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
            $token = Auth::$plugin->getOAuth()->callback('video-picker', $source);

            if (!$token) {
                Session::setError('video-picker', Craft::t('video-picker', 'Unable to fetch token.'), true);

                return $this->redirect($origin);
            }

            // Save the token to the Auth plugin, with a reference to this source
            $token->reference = $source->id;
            Auth::$plugin->getTokens()->upsertToken($token);
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
        $sourceHandle = $this->request->getRequiredParam('source');

        if (!($source = VideoPicker::$plugin->getSources()->getSourceByHandle($sourceHandle))) {
            return $this->asFailure(Craft::t('video-picker', 'Unable to find source “{source}”.', ['source' => $sourceHandle]));
        }

        // Delete all tokens for this source
        Auth::$plugin->getTokens()->deleteTokenByOwnerReference('video-picker', $source->id);

        return $this->asModelSuccess($source, Craft::t('video-picker', '{provider} disconnected.', ['provider' => $source->providerName]), 'source');
    }

}
