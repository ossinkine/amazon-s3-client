<?php

$config = Symfony\CS\Config\Config::create();
$config->fixers(array(
    'align_double_arrow',
    'ordered_use',
));
$config->setDir(__DIR__);
$config->getFinder()->exclude('cache');

return $config;
