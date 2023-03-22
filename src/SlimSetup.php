<?php

namespace MP;

use Slim\App;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Throwable;

class SlimSetup {
	private static App $slim;
	
	public static function createResponse(): Response {
		return self::$slim->getResponseFactory()->createResponse();
	}
	
	public static function getSlim(): App {
		return self::$slim;
	}
	
	public static function setup(): void {
		self::$slim = AppFactory::create();
		
		//Not sure what this actually does, but I do not seem to need it.
		#$app->addRoutingMiddleware();
		
		self::setupCorsMiddleware();
		self::setupErrorHandling();
	}
	
	public static function run(): void {
		self::$slim->run();
	}
	
	private static function setupCorsMiddleware(): void {
		self::$slim->add(function (Request $request, RequestHandler $handler) {
			if (isset($_SERVER['HTTP_ORIGIN'])) {
				$origin = $_SERVER['HTTP_ORIGIN'];
				if (in_array($origin, ['http://localhost:5173', 'https://lwmods.ecconia.com'])) {
					header('Access-Control-Allow-Origin: ' . $origin);
					header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
					header('Access-Control-Allow-Headers: DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range');
					
					if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
						header('Access-Control-Max-Age: 1728000');
						header('Content-Type: text/plain charset=UTF-8');
						header('Content-Length: 0');
						
						//Discard original request and answer:
						$response = self::createResponse();
						return $response->withStatus(204);
					}
				}
			}
			
			//Continue normally:
			return $handler->handle($request);
		});
	}
	
	private static function setupErrorHandling(): void {
		$errorMiddleware = self::$slim->addErrorMiddleware(true, false, false);
		
		$errorMiddleware->setErrorHandler(
			HttpNotFoundException::class,
			function (Request $request, Throwable $exception, bool $displayErrorDetails) {
				$response = self::createResponse();
				$response->getBody()->write('404 NOT FOUND');
				return $response->withStatus(404);
			}
		);
		
		// Set the Not Allowed Handler
		$errorMiddleware->setErrorHandler(
			HttpMethodNotAllowedException::class,
			function (Request $request, Throwable $exception, bool $displayErrorDetails) {
				$response = self::createResponse();
				$response->getBody()->write('405 NOT ALLOWED');
				return $response->withStatus(405);
			}
		);
		
		$errorMiddleware->setDefaultErrorHandler(function (Request $request, Throwable $exception, bool $displayErrorDetails) {
			return ResponseFactory::fromException($exception);
		});
	}
}