<?php

include __DIR__ . '../../../header.php';

use Supabase\GoTrue\GoTrueClient;

$path = '/auth/v1';

$client = new GoTrueClient($reference_id, $api_key, [
	'autoRefreshToken'   => false,
	'persistSession'     => true,
	'storageKey'         => $api_key,
], $domain, $scheme, $path);

$params = [
	'type'  => 'recovery',
	'email' => 'email@example.com',
];

$response = $client->admin->generateLink($params);
if ($response['error']) {
	dump($response);
} else {
	dump($response['data']);
}
dump($response);
