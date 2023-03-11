<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../vendor/autoload.php';

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Factory\AppFactory;
use Slim\Exception\HttpNotFoundException;

#echo 'Lololooo<br />' . PHP_EOL;
#$test = new MP\Test();
#$test->print_nonsense();

$app = AppFactory::create();

#$app->addRoutingMiddleware();

$errorMiddleware = $app->addErrorMiddleware(true, false, false);

$errorMiddleware->setErrorHandler(
	HttpNotFoundException::class,
	function (Request $request, Throwable $exception, bool $displayErrorDetails
) use ($app) {
	$response = $app->getResponseFactory()->createResponse();
	$response->getBody()->write('404 NOT FOUND');
	return $response->withStatus(404, 'page not found');
});

// Set the Not Allowed Handler
$errorMiddleware->setErrorHandler(
	HttpMethodNotAllowedException::class,
	function (Request $request, Throwable $exception, bool $displayErrorDetails
) use ($app) {
	$response = $app->getResponseFactory()->createResponse();
	$response->getBody()->write('405 NOT ALLOWED');
	return $response->withStatus(405);
});
/*
$errorMiddleware->setDefaultErrorHandler(function (Request $request, Throwable $exception, bool $displayErrorDetails
) use ($app) {
	$response = $app->getResponseFactory()->createResponse();
	$response->getBody()->write('Exception: ' . get_class($exception) . ' YOP!');
	return $response->withStatus(500);
});
*/

require_once '../src/handlers/handlerList.php';

$app->get('/', function (Request $request, Response $response, $args) {
	$response->getBody()->write("No u!");
	return $response;
});

//For some reasons the normal 404 error handler is providing a debugging feedback - this.
#$app->any('{route:.*}', function(Request $request, Response $response) {
#	$response = $response->withStatus(404, 'page not found');
#	return $response;
#});

$app->run();
