<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use MP\Handlers\HandlerList;
use MP\SlimSetup;

//Load environment variables:
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$dotenv->required(['DB_NAME', 'DB_USER', 'DB_PASSWORD'])->notEmpty();

SlimSetup::setup();
//Any error above this point will result in a CORS issue in the browser. Mostly these are failed setup errors though.
HandlerList::initializeRouteHandlers();
SlimSetup::run();
