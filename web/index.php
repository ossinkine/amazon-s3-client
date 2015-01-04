<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Application(array('debug' => true));
$app->run();
