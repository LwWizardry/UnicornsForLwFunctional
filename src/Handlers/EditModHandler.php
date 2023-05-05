<?php

namespace MP\Handlers;

use MP\DbEntries\ModDetails;
use MP\DbEntries\ModSummary;
use MP\DbEntries\User;
use MP\ErrorHandling\BadRequestException;
use MP\Helpers\JsonValidator;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\Helpers\UTF8Helper;
use MP\PDOWrapper;
use MP\ResponseFactory;
use MP\SlimSetup;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EditModHandler {
	public static function initializeRouteHandlers(): void {
		SlimSetup::getSlim()->post('/mod/post', self::addMod(...));
		SlimSetup::getSlim()->get('/mod/user-list', self::listModsOfUser(...));
		SlimSetup::getSlim()->post('/mod/edit', self::editMod(...));
	}
	
	public static function editMod(Request $request, Response $response): Response {
		//Get user making the request:
		$authToken = SlimSetup::expectAuthorizationHeader($request);
		$user = User::fromSession($authToken);
		//At this point it is validated, that a logged-in user is making the request.
		
		//Parse data for this request:
		$content = $request->getBody()->getContents();
		$jsonContent = JsonValidator::parseJson($content);
		$jsonData = JsonValidator::getObject($jsonContent, 'data');
		
		$modIdentifier = JsonValidator::getString($jsonData, 'identifier');
		$newTitle = JsonValidator::getStringNullable($jsonData, 'newTitle');
		$newCaption = JsonValidator::getStringNullable($jsonData, 'newCaption');
		if($newTitle === null && $newCaption === null) {
			throw new BadRequestException('No change in request.');
		}
		//TBI: Is trimming like this sufficient?
		$newTitle = $newTitle !== null ? trim($newTitle) : null;
		$newCaption = $newCaption !== null ? trim($newCaption) : null;
		//TBI: Error type, front-end should have caught this... (for all following checks)
		$result = self::checkTitleCaption($newTitle, $newCaption);
		if($result !== null) {
			return ResponseFactory::writeFailureMessage($response, $result);
		}
		//At this point, the request is valid.
		
		//Fetch mod data, to check what has to be changed:
		$modDetails = ModDetails::getModFromIdentifier($modIdentifier);
		if($modDetails === null) {
			throw new BadRequestException('Mod does not exist.');
		}
		if($modDetails->getUser()->getIdentifier() !== $user->getIdentifier()) {
			//TODO: Once a mod can be hidden. Check if it is public, and adjust the error accordingly.
			throw new BadRequestException('No permission to edit this mod.');
		}
		//Request may actually be performed - as mod exist and user has permissions for it.
		
		$builder = QueryBuilder::update('mods');
		if($newTitle !== null) {
			$builder->setValue('title', $newTitle);
		}
		if($newCaption !== null) {
			$builder->setValue('caption', $newCaption);
		}
		$builder->whereValue('identifier', $modIdentifier);
		try {
			$builder->execute();
		} catch (PDOException $e) {
			if(PDOWrapper::isUniqueConstrainViolation($e)) {
				return ResponseFactory::writeFailureMessage($response, 'Mod title is already used by another mod.');
			}
			throw $e;
		}
		//TBI: Update fields?
		
		return ResponseFactory::writeJsonData($response, []);
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
		
		//TBI: Is trimming like this sufficient?
		$title = trim($title);
		$caption = trim($caption);
		//TBI: Error type, front-end should have caught this... (for all following checks)
		$result = self::checkTitleCaption($title, $caption);
		if($result !== null) {
			return ResponseFactory::writeFailureMessage($response, $result);
		}
		//At this point the request data is valid.
		
		$modSummary = ModSummary::addNewMod($title, $caption, $user);
		if($modSummary === null) {
			return ResponseFactory::writeFailureMessage($response, 'A mod with a title like this already exists!');
		}
		
		//Mod entry in theory finished...
		
		return ResponseFactory::writeJsonData($response, [
			'identifier' => $modSummary->getIdentifier(),
		]);
	}
	
	public static function listModsOfUser(Request $request, Response $response): Response {
		$params = $request->getQueryParams();
		if(!array_key_exists('identifier', $params)) {
			return ResponseFactory::writeBadRequestError($response, 'Missing "identifier" query in URL.');
		}
		$identifier = $params['identifier'];
		if(empty($identifier)) {
			return ResponseFactory::writeBadRequestError($response, 'Missing "identifier" query value in URL.');
		}
		//Now the user identifier is known. Try getting a user for it:
		$user = User::fromIdentifier($identifier);
		if($user === null) {
			return ResponseFactory::writeJsonData($response, []); //No mod.
			//return ResponseFactory::writeBadRequestError($response, 'User does not exist.');
		}
		//Now the user in question is there...
		
		$mods = ModSummary::getSummariesForUser($user);
		return ResponseFactory::writeJsonData($response, array_map(
			function ($mod) {
				return $mod->asFrontEndJSON();
			},
			$mods
		));
	}
	
	private static function checkTitleCaption(null|string $title, null|string $caption): null|string {
		if($title !== null) {
			if(!UTF8Helper::isUTF8($title)) {
				throw new BadRequestException('Invalid UTF-8 sequence for title');
			}
			$titleLength = mb_strlen($title, 'UTF-8');
			if($titleLength < 3) {
				return 'Title must have at least 3 letters.';
			}
			if($titleLength > 50) {
				return 'Title must not be longer than 50 letters.';
			}
		}
		if($caption !== null) {
			if(!UTF8Helper::isUTF8($caption)) {
				throw new BadRequestException('Invalid UTF-8 sequence for title');
			}
			$captionLength = mb_strlen($caption, 'UTF-8');
			if($captionLength < 10) {
				return 'Caption must have at least 10 letters.';
			}
			if($captionLength > 200) {
				return 'Caption must not be longer than 200 letters.';
			}
		}
		return null;
	}
}
