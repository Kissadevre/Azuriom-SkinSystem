<?php

$loader = require dirname(__DIR__, 3).'/vendor/autoload.php';

$loader->addPsr4('Azuriom\\Plugin\\SkinSystem\\', dirname(__DIR__).'/src/');
$loader->addPsr4('Azuriom\\Plugin\\SkinSystem\\Tests\\', __DIR__.'/');
