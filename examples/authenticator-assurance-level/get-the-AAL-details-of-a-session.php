<?php

include __DIR__ . '../../header.php';

use Supabase\GoTrue\GoTrueClient;

$path = '/auth/v1';

$client = new GoTrueClient($reference_id, $api_key, [
	'autoRefreshToken'   => false,
	'persistSession'     => true,
	'storageKey'         => $api_key,
], $domain, $scheme, $path);

$userData = [
	'email'                => $ramdom_email,
	'password'             => '12345678',
	'email_confirm'        => true,
];

$new_user = $client->admin->createUser($userData);

$response = $client->signInWithPassword([
	'email'                => $ramdom_email,
	'password'             => '12345678',
]);
$access_token = $response['data']['access_token'];

$user = $client->_getAuthenticatorAssuranceLevel($access_token);
dump($user);
