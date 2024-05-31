<?php

include __DIR__ . '../../header.php';

use Supabase\GoTrue\GoTrueClient;

$path = '/auth/v1';

$client = new GoTrueClient($reference_id, $api_key, [
	'autoRefreshToken'   => false,
	'persistSession'     => true,
	'storageKey'         => $api_key,
], $domain, $scheme, $path);

$email = $ramdom_email;
$result = $client->admin->createUser([
	'email'                => $email,
	'password'             => 'example-password',
	'email_confirm'        => true,
]);

$result = $client->signInWithPassword([
	'email'                => $email,
	'password'             => 'example-password',
]);
dump($result);
$uid = $result['data']['user']['id'];
$access_token = $result['data']['access_token'];
$result = $client->refreshSession($access_token);
dump($result);
$result = $client->admin->deleteUser($uid);
