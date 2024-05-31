<?php

include __DIR__.'/../vendor/autoload.php';

if (!defined('STDIN'))  define('STDIN',  fopen('php://stdin',  'rb'));
if (!defined('STDOUT')) define('STDOUT', fopen('php://stdout', 'wb'));
if (!defined('STDERR')) define('STDERR', fopen('php://stderr', 'wb'));

$whoops = new \Whoops\Run;
$whoops->pushHandler(new \Whoops\Handler\PrettyPageHandler);
$whoops->register();

use Dotenv\Dotenv;

$dotenv = Dotenv::createUnsafeImmutable(dirname(__DIR__, 1));
$dotenv->load();

$characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
$random_string = '';
$domain = 'example.com'; // change this to your desired domain
$length = 10;
for ($i = 0; $i < $length; $i++) {
	$random_string .= $characters[rand(0, strlen($characters) - 1)];
}
$ramdom_email = $random_string.'@'.$domain;
$api_key = getenv('API_KEY');
$reference_id = getenv('REFERENCE_ID');
$service_api_key = getenv('SERVICE_ROLE_KEY');
$scheme = getenv('SCHEME');
$domain = getenv('DOMAIN');

// dump(get_declared_classes()); exit;