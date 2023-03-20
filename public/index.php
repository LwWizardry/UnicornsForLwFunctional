<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use MP\SlimSetup;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

//Load environment variables:
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();
$dotenv->required(['DB_NAME', 'DB_USER', 'DB_PASSWORD'])->notEmpty();

SlimSetup::setup();

require_once '../src/handlers/handlerList.php';

SlimSetup::getSlim()->get('/', function (Request $request, Response $response, $args) {
	$response->getBody()->write("No u!");
	return $response;
});

SlimSetup::run();