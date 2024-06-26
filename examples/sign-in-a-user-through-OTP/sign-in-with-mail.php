<?php

include __DIR__ . '../../header.php';

use Supabase\GoTrue\GoTrueClient;

$path = '/auth/v1';

$client = new GoTrueClient($reference_id, $api_key, [
	'autoRefreshToken'   => false,
	'persistSession'     => true,
	'storageKey'         => $api_key,
], $domain, $scheme, $path);

$response = $client->signInWithOtp([
	'email'                => 'adolfo@zerocopylabs.com',
	'gotrue_meta_security' => ['captcha_token' => $options['captchaToken'] ?? null],
	'options'              => [
		'emailRedirectTo' => 'https://example.com/welcome',
	],
]);
dump($response);
