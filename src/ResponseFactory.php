<?php

namespace MP;

use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

class ResponseFactory {
	public static function writeJson(Response $response, $data): Response {
		$response->getBody()->write(json_encode($data));
		return $response->withHeader('Content-Type', 'application/json');
	}
	
	public static function writeJsonData(Response $response, $data): Response {
		return self::writeJson($response, [
			'data' => $data,
		]);
	}
	
	public static function writeJsonFailure(Response $response, $data): Response {
		return self::writeJson($response, [
			'failure' => $data,
		]);
	}
	
	public static function writeFailureMessage(Response $response, string $message): Response {
		return self::writeJson($response, [
			'failure' => [
				'user-error' => $message,
			],
		]);
	}
	
	public static function writeFailureMessageActions(Response $response, string $message, array $actions): Response {
		return self::writeJson($response, [
			'failure' => [
				'user-error' => $message,
				'actions' => $actions,
			],
		]);
	}
	
	public static function fromException(Throwable $exception): Response {
		if(is_a($exception, BadRequestException::class)) {
			return self::writeBadRequestError(SlimSetup::createResponse(), $exception->getMessage());
		} else if(is_a($exception, InternalDescriptiveException::class)) {
			$content = [
				'error' => [
					'type' => 'internal-descriptive',
					'message' => $exception->getMessage(),
				],
			];
		} else {
			$message = $exception->getMessage();
			//Primitive way to prevent any sensitive DB data to leak.
			// PDO at least includes 'localhost' and the connecting username of the DB.
			$message = str_replace($_ENV['DB_USER'], '<redacted>', $message);
			$message = str_replace($_ENV['DB_PASSWORD'], '<redacted>', $message);
			$message = str_replace($_ENV['DB_NAME'], '<redacted>', $message);
			$message = str_replace('localhost', '<redacted>', $message);
			$content = [
				'error' => [
					'type' => 'internal',
					'class' => get_class($exception),
					'code' => $exception->getCode(),
					'message' => $message,
					'trace' => $exception->getTraceAsString(),
				],
			];
		}
		
		$response = SlimSetup::createResponse();
		$response = self::writeJson($response, $content);
		return $response->withStatus(500);
	}
	
	public static function writeBadRequestError(Response $response, string $message, int $code = 400): Response {
		$content = [
			'error' => [
				'type' => 'bad-request',
				'message' => $message,
			],
		];
		
		return self::writeJson($response, $content)->withStatus($code);
	}
}
