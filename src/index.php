<?php
/**
 * Created by PhpStorm.
 * User: tengzbiao
 * Date: 2019/5/12
 * Time: 下午6:58
 */

header("Content-Type: text/html;charset=utf-8");
ini_set('memory_limit', '500M');
date_default_timezone_set("PRC");
set_time_limit(0);
error_reporting(0);

require_once(__DIR__ . '/server.php');

$server = new Server('0.0.0.0', '8888');
$server->run();