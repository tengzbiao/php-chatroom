<?php

header("Content-Type: text/html;charset=utf-8");
ini_set('memory_limit', '500M');
date_default_timezone_set("PRC");
set_time_limit(0);
error_reporting(0);

require __DIR__ . '/vendor/autoload.php';

$server = new App\Server('0.0.0.0', '8888');
$server->run();