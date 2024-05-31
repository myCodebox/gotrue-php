<?php

include __DIR__ . './header.php';

use Supabase\GoTrue\GoTrueClient;

$path = '/auth/v1';

$client = new GoTrueClient($reference_id, $api_key, [
	'autoRefreshToken'   => false,
	'persistSession'     => true,
	'storageKey'         => $api_key,
], $domain, $scheme, $path);

$response = $client->signUp([
	'email'                => 'example@email.com',
	'password'             => 'example-password',
	'options'              => [
		'emailRedirectTo' => 'https://example.com/welcome',
	],
	'gotrue_meta_security' => ['captcha_token' => $options['captchaToken'] ?? null],
]);
dump($response);
