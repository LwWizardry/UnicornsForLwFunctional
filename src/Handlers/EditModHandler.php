<?php

namespace MP\Handlers;

use MP\DbEntries\ModSummary;
use MP\DbEntries\User;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\JsonValidator;
use MP\Helpers\UniqueInjectorHelper;
use MP\PDOWrapper;
use MP\ResponseFactory;
use MP\SlimSetup;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EditModHandler {
	public static function initializeRouteHandlers(): void {
		SlimSetup::getSlim()->post('/mod/post', self::addMod(...));
	}
	
	public static function addMod(Request $request, Response $response): Response {
		$authToken = SlimSetup::expectAuthorizationHeader($request);
		$user = User::fromSession($authToken);
		//At this point it is validated, that a logged-in user is making the request.
		
		//Validate data sent by the client makes sense. (Where data?)
		$content = $request->getBody()->getContents();
		$jsonContent = JsonValidator::parseJson($content);
		$jsonData = JsonValidator::getObject($jsonContent, 'data');
		
		$title = JsonValidator::getString($jsonData, 'title');
		$caption = JsonValidator::getString($jsonData, 'caption');
		
		if(strlen($title) > 50) {
			//TBI: Error type, front-end should have caught this...
			return ResponseFactory::writeFailureMessage($response, 'Title is too long.');
		}
		$captionLength = strlen($caption);
		if($captionLength < 10) {
			//TBI: Error type, front-end should have caught this...
			return ResponseFactory::writeFailureMessage($response, 'Caption is too short.');
		}
		if($captionLength > 200) {
			//TBI: Error type, front-end should have caught this...
			return ResponseFactory::writeFailureMessage($response, 'Caption is too long.');
		}
		//At this point the request data is valid.
		
		//TODO: Also somehow store somewhere which user created something... Ehm...
		
		$modSummary = ModSummary::addNewMod($title, $caption);
		if($modSummary === null) {
			return ResponseFactory::writeFailureMessage($response, 'A mod with a title like this already exists!');
		}
		
		//Mod entry in theory finished...
		
		return ResponseFactory::writeJsonData($response, [
			'identifier' => $modSummary->getIdentifier(),
		]);
	}
}
