<?php

include __DIR__ . '../../../header.php';

use Supabase\GoTrue\GoTrueAdminApi;

$path = '/auth/v1';

$client = new GoTrueAdminApi($reference_id, $api_key, [
	'autoRefreshToken'   => false,
	'persistSession'     => true,
	'storageKey'         => $api_key,
], $domain, $scheme, $path);

$response = $client->listUsers([
	'page'   => 1,
	'perPage' => 2,
]);
dump($response);
