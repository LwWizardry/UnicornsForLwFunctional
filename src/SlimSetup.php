<?php

namespace MP;

use MP\ErrorHandling\BadRequestException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Server\RequestHandlerInterface as RequestHandler;
use Slim\App;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Slim\Factory\AppFactory;
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
				if (in_array($origin, [
					$_ENV['PRODUCTION_FRONTEND_URL'], //Production/Test-Server URL
					'http://localhost:5173', //Direct Development URL
					'http://lwmods.localhost', //Built Development URL
				])) {
					header('Access-Control-Allow-Origin: ' . $origin);
					header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
					header('Access-Control-Allow-Headers: Authorization,DNT,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type,Range');
					
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
			function () {
				$response = self::createResponse();
				$response->getBody()->write('404 NOT FOUND');
				return $response->withStatus(404);
			}
		);
		
		// Set the Not Allowed Handler
		$errorMiddleware->setErrorHandler(
			HttpMethodNotAllowedException::class,
			function () {
				$response = self::createResponse();
				$response->getBody()->write('405 NOT ALLOWED');
				return $response->withStatus(405);
			}
		);
		
		$errorMiddleware->setDefaultErrorHandler(function (Request $request, Throwable $exception) {
			return ResponseFactory::fromException($exception);
		});
	}
	
	public static function expectAuthorizationHeader(Request $request): string {
		if (!$request->hasHeader('Authorization')) {
			throw new BadRequestException('Missing authorization header');
		}
		$sessionID = $request->getHeader('Authorization');
		if(count($sessionID) !== 1) {
			throw new BadRequestException('Expected exactly one authorization header/values, got ' . count($sessionID));
		}
		if(preg_match('/Bearer ([a-zA-Z0-9+\/]+=*)/m', $sessionID[0], $matches)) {
			return $matches[1];
		}
		throw new BadRequestException('Malformed authorization header');
	}
}
