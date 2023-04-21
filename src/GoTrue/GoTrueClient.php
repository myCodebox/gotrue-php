<?php

namespace Supabase\GoTrue;

use Psr\Http\Message\ResponseInterface;
use Supabase\Util\AuthSessionMissingError;
use Supabase\Util\Constants;
use Supabase\Util\GoTrueError;
use Supabase\Util\Helpers;
use Supabase\Util\Request;
use Supabase\Util\Storage;

class GoTrueClient
{
    protected $stateChangeEmitters;
    protected $networkRetries = 0;
    protected $refreshingDeferred;
    protected $initializePromise;
    protected $detectSessionInUrl;
    protected $settings;
    protected $inMemorySession;
    protected $storageKey;
    protected $autoRefreshToken;
    protected $persistSession;
    protected $flowType = 'implicit';
    protected $storage;
    public GoTrueAdminApi $admin;
    public GoTrueMFAApi $mfa;
    protected $url;
    protected $headers;

    /**
     * Get the url.
     */
    public function __getUrl(): string
    {
        return $this->url;
    }

    /**
     * Get the headers.
     */
    public function __getHeaders(): array
    {
        return $this->headers;
    }

    public function __construct($reference_id, $api_key, $options = [], $domain = 'supabase.co', $scheme = 'https', $path = '/auth/v1')
    {
        $headers = ['Authorization' => "Bearer {$api_key}", 'apikey' => $api_key];
        $this->url = !empty($reference_id) ? "{$scheme}://{$reference_id}.{$domain}{$path}" : "{$scheme}://{$domain}{$path}";
        $this->settings = array_merge(Constants::getDefaultHeaders(), $options);
        $this->storageKey = $this->settings['storageKey'] ?? null;
        $this->autoRefreshToken = $this->settings['autoRefreshToken'] ?? null;
        $this->persistSession = $this->settings['persistSession'] ?? null;
        $this->detectSessionInUrl = $this->settings['detectSessionInUrl'] ?? false;
        

        if (!$this->url) {
            throw new \Exception('No URL provided');
        }

        $this->headers = array_merge(Constants::getDefaultHeaders(), $headers);

        /**
         * Namespace for the GoTrue admin methods.
         * These methods should only be used in a trusted server-side environment.
         */
        $this->admin = new GoTrueAdminApi($reference_id, $api_key, [
            'url'     => $this->url,
            'headers' => $this->headers,
        ], $domain, $scheme, $path);

        /**
         * Namespace for the MFA methods.
         */
        $this->mfa = new GoTrueMFAApi($reference_id, $api_key, [
            'url'     => $this->url,
            'headers' => $this->headers,
        ], $domain, $scheme, $path);

        $this->storage = new Storage();
        $this->stateChangeEmitters = [];
        $this->initializePromise = $this->initialize();
    }

    public function initialize()
    {
        if (!$this->initializePromise) {
            $this->initializePromise = $this->_initialize();
        }

        return $this->initializePromise;
    }

    public function _initialize()
    {
        $data = [];
        if ($this->initializePromise) {
            return $this->initializePromise;
        }

        if ($this->detectSessionInUrl && $this->_isImplicitGrantFlow()) {
            try {
                $data = $this->_getSessionFromUrl();
            } catch (\Exception $e) {
                return ['error' => $e];
            }

            $session = $data['session'];

            $this->_saveSession($session);
            $this->_notifyAllSubscribers('SIGNED_IN', $session);

            if ($data['redirectType'] == 'recovery') {
                $this->_notifyAllSubscribers('PASSWORD_RECOVERY', $session);
            }

            return ['error' => null];
        }

        $this->_recoverAndRefresh();

        return ['error' => null];
    }

    public function __request($method, $url, $headers, $body = null): ResponseInterface
    {
        return Request::request($method, $url, $headers, $body);
    }

    private function _recoverAndRefresh()
    {
    }

    private function _removeSession()
    {
    }

    /**
     * Creates a new user.
     *
     * Be aware that if a user account exists in the system you may get back an
     * error message that attempts to hide this information from the user.
     *
     * @returns A logged-in session if the server has "autoconfirm" ON
     * @returns A user if the server has "autoconfirm" OFF
     */
    public function signUp($credentials)
    {
        try {
            $this->_removeSession();
            $headers = array_merge($this->headers, ['Content-Type' => 'application/json']);
            $body = json_encode($credentials);
            if (isset($credentials['email'])) {
                $response = $this->__request('POST', $this->url . '/signup', $headers, $body);
            } elseif (isset($credentials['phone'])) {
                $response = $this->__request('POST', $this->url . '/signup', $headers, $body);
            } else {
                throw new GoTrueError('You must provide either an email or phone number and a password');
            }

            $data = json_decode($response->getBody(), true);
            $session = isset($data['session']) ? $data['session'] : null;
            $user = $data;

            if (isset($data['session'])) {
                $this->_saveSession($session);
                $this->_notifyAllSubscribers('SIGNED_IN', $session);
            }

            return ['data' => ['user' => $user, 'session' => $session], 'error' => null];
        } catch (\Exception $e) {
            if (GoTrueError::isGoTrueError($e)) {
                return ['data' => ['user' => null, 'session' => null], 'error' => $e];
            }
            throw $e;
        }
    }

    /**
     * Log in an existing user with an email and password or phone and password.
     *
     * Be aware that you may get back an error message that will not distinguish
     * between the cases where the account does not exist or that the
     * email/phone and password combination is wrong or that the account can only
     * be accessed via social login.
     */
    public function signInWithPassword($credentials)
    {
        try {
            $this->_removeSession();
            $headers = array_merge($this->headers, ['Content-Type' => 'application/json']);
            $body = json_encode($credentials);
            if (isset($credentials['email'])) {
                $response = $this->__request('POST', $this->url . '/token?grant_type=password', $headers, $body);
            } elseif (isset($credentials['phone'])) {
                $response = $this->__request('POST', $this->url . '/token?grant_type=password', $headers, $body);
            } else {
                throw new GoTrueError('You must provide either an email or phone number and a password');
            }

            $data = json_decode($response->getBody(), true);
            $session = isset($data['session']) ? $data['session'] : null;

            if (isset($data['session'])) {
                $this->_saveSession($session);
                $this->_notifyAllSubscribers('SIGNED_IN', $session);
            }

            return ['data' => $data, 'error' => null];
        } catch (\Exception $e) {
            if (GoTrueError::isGoTrueError($e)) {
                return ['data' => ['user' => null, 'session' => null], 'error' => $e];
            }

            throw $e;
        }
    }

    /**
     * Log in a user using magiclink or a one-time password (OTP).
     *
     * If the `{{ .ConfirmationURL }}` variable is specified in the email template, a magiclink will be sent.
     * If the `{{ .Token }}` variable is specified in the email template, an OTP will be sent.
     * If you're using phone sign-ins, only an OTP will be sent. You won't be able to send a magiclink for phone sign-ins.
     *
     * Be aware that you may get back an error message that will not distinguish
     * between the cases where the account does not exist or, that the account
     * can only be accessed via social login.
     *
     * Do note that you will need to configure a Whatsapp sender on Twilio
     * if you are using phone sign in with the 'whatsapp' channel. The whatsapp
     * channel is not supported on other providers
     * at this time.
     */
    public function signInWithOtp($credentials)
    {
        try {
            $this->_removeSession();
            $headers = array_merge($this->headers, ['Content-Type' => 'application/json']);
            $body = json_encode($credentials);
            if (isset($credentials['email'])) {
                $response = $this->__request('POST', $this->url . '/otp', $headers, $body);
            } elseif (isset($credentials['phone'])) {
                $response = $this->__request('POST', $this->url . '/otp', $headers, $body);
            } else {
                throw new GoTrueError('You must provide either an email or phone number and a password');
            }

            $data = json_decode($response->getBody(), true);
            $session = isset($data['session']) ? $data['session'] : null;

            if (isset($data['session'])) {
                $this->_saveSession($session);
                $this->_notifyAllSubscribers('SIGNED_IN', $session);
            }

            return ['data' => $data, 'error' => null];
        } catch (\Exception $e) {
            if (GoTrueError::isGoTrueError($e)) {
                return ['data' => ['user' => null, 'session' => null], 'error' => $e];
            }
            throw $e;
        }
    }

    /**
     * Gets the current user details if there is an existing session.
     * @param jwt Takes in an optional access token jwt. If no jwt is provided, getUser() will attempt to get the jwt from the current session.
     */
    public function getUser($jwt = null)
    {
        try {
            if (!$jwt) {
                $sessionResult = $this->getSession($jwt);
                $sessionData = $sessionResult['data'];
                $sessionError = $sessionResult['error'];

                if ($sessionError) {
                    throw $sessionError;
                }

                // Default to Authorization header if there is no existing session
                $jwt = $sessionData['session']['access_token'] ?? null;
            }
            $this->headers['Authorization'] = "Bearer {$jwt}";
            $url = $this->url . '/user';
            $headers = array_merge($this->headers, ['Content-Type' => 'application/json', 'noResolveJson' => true]);
            $response = $this->__request('GET', $url, $headers);
            $user = json_decode($response->getBody(), true);
            return $user;
        } catch (\Exception $e) {
            if (GoTrueError::isGoTrueError($e)) {
                return ['data' => ['user' => null], 'error' => $e];
            }

            throw $e;
        }
    }

    /**
     * Updates user data for a logged in user.
     */
    public function updateUser($attrs, $jwt = null, $options = [])
    {
        try {
            if (!$jwt) {
                $sessionResult = $this->getSession($jwt);
                $sessionData = $sessionResult['data'];
                $sessionError = $sessionResult['error'];

                if ($sessionError) {
                    throw $sessionError;
                }

                // Default to Authorization header if there is no existing session
                $jwt = $sessionData['session']['access_token'] ?? null;
            }
            $this->headers['Authorization'] = "Bearer {$jwt}";
            $redirectTo = isset($options['redirectTo']) ? "?redirect_to={$options['redirectTo']}" : null;
            $url = $this->url . '/user' . $redirectTo;
            $body = json_encode($attrs);
            $headers = array_merge($this->headers, ['Content-Type' => 'application/json', 'noResolveJson' => true]);
            $response = $this->__request('PUT', $url, $headers, $body);
            $data = json_decode($response->getBody(), true);

            return ['data' => $data, 'error' => null];
        } catch (\Exception $e) {
            if (GoTrueError::isGoTrueError($e)) {
                return ['data' => ['user' => null], 'error' => $e];
            }

            throw $e;
        }
    }

    /**
     * Returns a new session, regardless of expiry status.
     * Takes in an optional current session. If not passed in, then refreshSession() will attempt to retrieve it from getSession().
     * If the current session's refresh token is invalid, an error will be thrown.
     * @param currentSession The current session. If passed in, it must contain a refresh token.
     */
    public function refreshSession($jwt = null)
    {
        try {
            if (!$jwt) {
                $sessionResult = $this->getSession($jwt);
                $sessionData = $sessionResult['data'];
                $sessionError = $sessionResult['error'];

                if ($sessionError) {
                    throw $sessionError;
                }

                // Default to Authorization header if there is no existing session
                $jwt = $sessionData['session']['access_token'] ?? null;
            }

            $data = self::_callRefreshToken($jwt);

            return ['data' => $data, 'error' => null];
        } catch (\Exception $e) {
            if (GoTrueError::isGoTrueError($e)) {
                return ['data' => ['user' => null], 'error' => $e];
            }

            throw $e;
        }
    }

    /**
     * Sets the session data from the current session. If the current session is expired, setSession will take care of refreshing it to obtain a new session.
     * If the refresh token or access token in the current session is invalid, an error will be thrown.
     * @param currentSession The current session that minimally contains an access token and refresh token.
     */
    public function setSession($currentSession = [])
    {
        try {
            if (empty($currentSession['access_token']) || empty($currentSession['refresh_token'])) {
                throw new AuthSessionMissingError();
            }
            $timeNow = time();
            $expiresAt = $timeNow;
            $hasExpired = true;
            $session = null;
            $payload = Helpers::decodeJWTPayload($currentSession['access_token']);
            if (!empty($payload['exp'])) {
                $expiresAt = $payload['exp'];
                $hasExpired = $expiresAt <= $timeNow ? true : false;
            }

            if ($hasExpired) {
                $result = $this->_callRefreshToken($currentSession['refresh_token']);
                if (!empty($result['error'])) {
                    return ['data' => ['user' => null, 'session' => null], 'error' => $result['error']];
                }

                if (empty($result['session'])) {
                    return ['data' => ['user' => null, 'session' => null], 'error' => null];
                }
                $session = $result['session'];
            } else {
                $result = $this->getUser($currentSession['access_token']);
                if (!empty($result['error'])) {
                    throw $result['error'];
                }

                $session = [
                    'access_token'  => $currentSession['access_token'],
                    'refresh_token' => $currentSession['refresh_token'],
                    'user'          => $result['identities'],
                    'token_type'    => 'bearer',
                    'expires_in'    => $expiresAt - $timeNow,
                    'expires_at'    => $expiresAt,
                ];

                $this->_saveSession($session);
                $this->_notifyAllSubscribers('SIGNED_IN', $session);
            }

            return ['data' => ['user' => $session['user'], 'session' => $session], 'error' => null];
        } catch (\Exception $e) {
            if (isAuthError($e)) {
                return ['data' => ['session' => null, 'user' => null], 'error' => $e];
            }

            throw $e;
        }
    }

    /**
     * Inside a browser context, `sign_out` will remove the logged in user from the
     * browser session and log them out - removing all items from localstorage and
     * then trigger a `"SIGNED_OUT"` event.
     * For server-side management, you can revoke all refresh tokens for a user by
     * passing a user's JWT through to `api.sign_out`.
     * There is no way to revoke a user's access token jwt until it expires.
     * It is recommended to set a shorter expiry on the jwt for this reason.
     */
    public function signOut($access_token = null)
    {
        $session = $this->getSession($access_token);
        $access_token = $session ? $session['access_token'] : null;

        if ($access_token) {
            $this->admin->signOut($access_token);
        }

        $this->_removeSession();
        $this->_notifyAllSubscribers("SIGNED_OUT", null);
    }

    /**
     * {@see GoTrueMFAApi#listFactors}
     */
    public function listFactors($jwt)
    {
        try {
            $user = $this->getUser($jwt);

            $factors = isset($user['factors']) ? $user['factors'] : [];
            $totp = array_filter($factors, function ($factor) {
                return $factor['factor_type'] === 'totp' && $factor['status'] === 'verified';
            });

            return ['data' => ['all' => $factors, 'totp' => $totp], 'error' => null];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Sends a password reset request to an email address.
     * @param email The email address of the user.
     * @param options.redirectTo The URL to send the user to after they click the password reset link.
     * @param options.captchaToken Verification token received when the user completes the captcha on the site.
     */
    public function resetPasswordForEmail(string $email, array $options = []): array
    {
        $codeChallenge = null;
        $codeChallengeMethod = null;

        if ($this->flowType === 'pkce') {
            $codeVerifier = Helpers::generatePKCEVerifier();
            $this->setItemAsync($this->storage, "{$this->storageKey}-code-verifier", $codeVerifier);
            $codeChallenge = Helpers::generatePKCEChallenge($codeVerifier);
            $codeChallengeMethod = $codeVerifier === $codeChallenge ? 'plain' : 's256';
        }

        try {
            $params = [
                'email' => $email,
                'code_challenge' => $codeChallenge,
                'code_challenge_method' => $codeChallengeMethod,
                'gotrue_meta_security' => ['captcha_token' => $options['captchaToken']],
            ];

            $url = $this->url . '/recover';
            $body = json_encode($params);
            $headers = array_merge($this->headers, ['Content-Type' => 'application/json', 'noResolveJson' => true]);
            $response = $this->__request('PUT', $url, $headers, $body);
            $data = json_decode($response->getBody(), true);

            return [
                'data' => $data,
                'error' => null,
            ];
        } catch (\Exception $error) {
            if (isAuthError($error)) {
                return [
                    'data' => null,
                    'error' => $error,
                ];
            }

            throw $error;
        }
    }

    /**
     * Returns the session, refreshing it if necessary.
     * The session returned can be null if the session is not detected which can happen in the event a user is not signed-in or has logged out.
     */
    public function getSession($access_token)
    {
        return ['access_token' => $access_token];
    }

    /**
     * {@see GoTrueMFAApi#getAuthenticatorAssuranceLevel}
     */
    public function _getAuthenticatorAssuranceLevel($access_token)
    {
        try {
            $sessionResponse = $this->getUser($access_token);
            $session = $sessionResponse;
            $sessionError = isset($sessionResponse['error']) ? $sessionResponse['error'] : false;

            if ($sessionError) {
                $response['data'] = null;
                $response['error'] = $sessionError;

                return $response;
            }

            if (!$session) {
                $response['data']['currentLevel'] = null;
                $response['data']['nextLevel'] = null;
                $response['data']['currentAuthenticationMethods'] = [];
                $response['error'] = null;

                return $response;
            }

            $payload = Helpers::decodeJWTPayload($access_token);

            $currentLevel = null;

            if (isset($payload['aal'])) {
                $currentLevel = $payload['aal'];
            }

            $nextLevel = $currentLevel;

            $session['factors'] = $session['factors'] ?? [];

            $verifiedFactors = array_filter($session['factors'], function ($factor) {
                return $factor['status'] === 'verified';
            });

            if (count($verifiedFactors) > 0) {
                $nextLevel = 'aal2';
            }

            $currentAuthenticationMethods = $payload['amr'] ?? [];

            $response['data']['currentLevel'] = $currentLevel;
            $response['data']['nextLevel'] = $nextLevel;
            $response['data']['currentAuthenticationMethods'] = $currentAuthenticationMethods;
            $response['error'] = null;
        } catch (\Exception $e) {
            $response['data'] = null;
            $response['error'] = $e->getMessage();
        }

        return $response;
    }

    private function _callRefreshToken(string $refreshToken)
    {
        try {
            if (!$refreshToken) {
                throw new AuthSessionMissingError();
            }

            $data = $this->_refreshAccessToken($refreshToken);

            if (!$data['session']) {
                throw new AuthSessionMissingError();
            }

            $this->_saveSession($data['session']);
            $this->_notifyAllSubscribers('TOKEN_REFRESHED', $data['session']);

            $result = ['session' => $data['session'], 'error' => null];

            return $result;
        } catch (\Exception $e) {
            if (isAuthError($e)) {
                $result = ['session' => null, 'error' => $e];

                return $result;
            }

            throw $e;
        }
    }

    public function _refreshAccessToken($refreshToken)
    {
        try {
            $url = $this->url . '/token?grant_type=refresh_token';
            print_r($refreshToken);
            $body = json_encode(['refresh_token' => $refreshToken]);
            $this->headers['Authorization'] = "Bearer {$refreshToken}";
            $headers = array_merge($this->headers, ['Content-Type' => 'application/json', 'noResolveJson' => true]);
            $response = $this->__request('POST', $url, $headers, $body);
            $data = json_decode($response->getBody(), true);

            return ['session' => $data, 'error' => null];
        } catch (\Exception $e) {
            throw $e;
        }
    }

    private function _saveSession($session = [])
    {
    }

    private function _notifyAllSubscribers($evnt, $session = [])
    {
    }

    private function setItemAsync($storage, $storageVerifier, $codeVerifier)
    {
    }

    private function _isImplicitGrantFlow()
    {
    }

    /**
     * Gets the session data from a URL string
     */
    private function _getSessionFromUrl()
    {
    }
}
