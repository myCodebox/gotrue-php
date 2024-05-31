<?php

include __DIR__ . '../../../header.php';

use Supabase\GoTrue\GoTrueClient;

$path = '/auth/v1';

$client = new GoTrueClient($reference_id, $api_key, [
	'autoRefreshToken'   => false,
	'persistSession'     => true,
	'storageKey'         => $api_key,
], $domain, $scheme, $path);

$userData = [
	'email'         => 'user@email.com',
	'email_confirm' => true,
];

$response = $client->admin->createUser($userData);
dump($response);
