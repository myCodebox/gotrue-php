<?php

/**
 * A PHP  class  client library to interact with Supabase GoTrue Api.
 *
 * Provides functions for Starts the enrollment process for a new Multi-Factor
 * Authentication (MFA) factor.
 */

namespace Supabase\GoTrue;

use Supabase\Util\Request;

class GoTrueMFAApi
{
	/**
	 * Fully qualified URL to the Supabase instance REST endpoint(s).
	 *
	 * @var string
	 */
	// protected string $url;
	protected $url;

	/**
	 * A header Bearer Token generated by the server in response to a login request
	 * [service key, not anon key].
	 *
	 * @var array
	 */
	// protected array $headers = [];
	protected $headers = [];
	protected $mfa;

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

	/**
	 * GoTrueMFAApi constructor.
	 *
	 * @param  string  $api_key  The anon or service role key
	 * @param  string  $reference_id  Reference ID
	 * @param  array  $options  Options
	 * @param  string  $domain  The domain pointing to api
	 * @param  string  $scheme  The api sheme
	 * @param  string  $path  The path to api
	 *
	 * @throws Exception
	 */
	public function __construct($reference_id, $api_key, $options = [], $domain = 'supabase.co', $scheme = 'https', $path = '/auth/v1')
	{
		$headers = ['Authorization' => "Bearer {$api_key}", 'apikey' => $api_key];
		$this->url = "{$scheme}://{$reference_id}.{$domain}{$path}";
		$this->headers = $headers ?? null;
		$this->mfa = [];
	}

	public function __request($method, $url, $headers, $body = null): array
	{
		$response = Request::request($method, $url, $headers, $body);

		return json_decode($response->getBody(), true);
	}

	/**
	 * Starts the enrollment process for a new Multi-Factor Authentication (MFA)
	 * factor. This method creates a new `unverified` factor.
	 * To verify a factor, present the QR code or secret to the user and ask them to add it to their
	 * authenticator app.
	 * The user has to enter the code from their authenticator app to verify it.
	 *
	 * Upon verifying a factor, all other sessions are logged out and the current session's authenticator level is promoted to `aal2`.
	 *
	 * @param  string  $jwt  The JSON Web Token for the current user session.
	 * @param  array  $params  factorType The type of factor being enrolled.
	 * @return array
	 *
	 * @throws Exception
	 */
	public function enroll($params = [], $jwt = null)
	{
		try {
			$url = $this->url.'/factors';
			$this->headers['Authorization'] = "Bearer {$jwt}";
			$body = json_encode($params);
			$headers = array_merge($this->headers, ['Content-Type' => 'application/json', 'noResolveJson' => true]);
			$response = $this->__request('POST', $url, $headers, $body);
			//$data = json_decode($response->getBody(), true);

			return ['data' => $response, 'error' => null];
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * Prepares a challenge used to verify that a user has access to a MFA factor.
	 *
	 * @param  string  $jwt  The JSON Web Token for the current user session.
	 * @param  string  $factor_id  ID of the factor to be challenged. Returned in enroll().
	 * @return array
	 *
	 * @throws Exception
	 */
	public function challenge($factor_id, $jwt)
	{
		try {
			$url = $this->url.'/factors/'.$factor_id.'/challenge';
			$this->headers['Authorization'] = "Bearer {$jwt}";
			$headers = array_merge($this->headers, ['Content-Type' => 'application/json', 'noResolveJson' => true]);
			$response = $this->__request('POST', $url, $headers);
			//$data = json_decode($response->getBody(), true);

			return ['data' => $response, 'error' => null];
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * Verifies a code against a challenge. The verification code is provided by the
	 * user by entering a code seen in their authenticator app.
	 *
	 * @param  string  $jwt  The JSON Web Token for the current user session.
	 * @param  string  $factor_id  ID of the factor to be challenged. Returned in enroll().
	 * @param  array  $params  challengeId ID of the challenge being verified. Returned in challenge().
	 *                         code    Verification code provided by the user.
	 * @return array
	 *
	 * @throws Exception
	 */
	public function verify($factor_id, $jwt, $params = [])
	{
		try {
			$url = $this->url.'/factors/'.$factor_id.'/verify';
			$this->headers['Authorization'] = "Bearer {$jwt}";
			$body = json_encode($params);
			$headers = array_merge($this->headers, ['Content-Type' => 'application/json', 'noResolveJson' => true]);
			$response = $this->__request('POST', $url, $headers, $body);
			//$data = json_decode($response->getBody(), true);

			return ['data' => $response, 'error' => null];
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * Helper method which creates a challenge and immediately uses the given
	 * code to verify against it thereafter. The verification code is provided
	 * by the user by entering a code seen in their authenticator app.
	 *
	 * @param  string  $jwt  The JSON Web Token for the current user session.
	 * @param  string  $factor_id  ID of the factor to be challenged. Returned in enroll().
	 * @param  array  $code  Verification code provided by the user.
	 * @return array
	 *
	 * @throws Exception
	 */
	public function challengeAndVerify($factor_id, $code, $jwt, $params = [])
	{
		try {
			$dataChallange = $this->challenge($factor_id, $jwt);

			if ($dataChallange['error']) {
				return ['data'=> null, 'error'=> $dataChallange['error']];
			}

			return $this->verify(
				$factor_id,
				$jwt,
				['challenge_id'=> $dataChallange['data']['id'] ?? null, 'code'=>$code]
			);
		} catch (\Exception $e) {
			throw $e;
		}
	}

	/**
	 * Unenroll removes a MFA factor. A user has to have an aal2 authenticator
	 * level in order to unenroll a verified factor.
	 *
	 * @param  string  $jwt  The JSON Web Token for the current user session.
	 * @param  string  $factor_id  ID of the factor being unenrolled.
	 * @return array
	 *
	 * @throws Exception
	 */
	public function unenroll($factor_id, $jwt)
	{
		try {
			$url = $this->url.'/factors/'.$factor_id;
			$this->headers['Authorization'] = "Bearer {$jwt}";
			$headers = array_merge($this->headers, ['Content-Type' => 'application/json', 'noResolveJson' => true]);
			$response = $this->__request('DELETE', $url, $headers);
			//$data = json_decode($response->getBody(), true);

			return ['data' => $response, 'error' => null];
		} catch (\Exception $e) {
			throw $e;
		}
	}
}
