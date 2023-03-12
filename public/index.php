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
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;

#echo 'Lololooo<br />' . PHP_EOL;
#$test = new MP\Test();
#$test->print_nonsense();

$app = AppFactory::create();

#$app->addRoutingMiddleware();

$app->add(function (Request $request, RequestHandler $handler) use ($app) {
    if(isset($_SERVER['HTTP_ORIGIN'])) {
        $origin = $_SERVER['HTTP_ORIGIN'];
        if(in_array($origin, ['http://localhost:5173', 'https://lwmods.ecconia.com'])) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
            header('Access-Control-Allow-Headers: DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range');

            if($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
                header('Access-Control-Max-Age: 1728000');
                header('Content-Type: text/plain charset=UTF-8');
                header('Content-Length: 0');

                //Discard original request and answer:
                $response = $app->getResponseFactory()->createResponse();
                return $response->withStatus(204);
            }
        }
    }

    //Continue normally:
    return $handler->handle($request);
});

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
