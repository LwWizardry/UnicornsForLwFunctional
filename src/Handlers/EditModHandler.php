<?php

namespace MP\Handlers;

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
		
		//TODO: Improve title validation, remove "'" and other funny characters.
		// Can probably be expanded on demand.
		//Sanitise title, by making it lowercase:
		$title_sane = mb_strtolower($title);
		
		//TODO: Also somehow store somewhere which user created something... Ehm...
		//Inject a mod entry into DB, Title/title_normalized/Caption - Warning: Title_sane can be a duplicate!
		
		try {
			$entryID = PDOWrapper::insertAndFetchColumn('
				INSERT INTO mods (title, title_sane, caption, created_at)
				VALUES (:title, :title_sane, :caption, UTC_TIMESTAMP())
				RETURNING id
			', [
				'title' => $title,
				'title_sane' => $title_sane,
				'caption' => $caption,
			]);
		} catch (PDOException $e) {
			if(PDOWrapper::isUniqueConstrainViolation($e)) {
				return ResponseFactory::writeFailureMessage($response, 'A mod with a title like this already exists!');
			}
			throw $e;
		}
		
		try {
			//Now that the entry is inserted, try to generate an identifier for it:
			$identifier = UniqueInjectorHelper::shortIdentifier('mods', $entryID);
		} catch (PDOException $e) {
			//Limit damage, by deleting the entry:
			PDOWrapper::deleteByIDSafe('mods', $entryID);
			throw $e;
		}
		if($identifier == null) {
			//Identifier was 'null', something went wrong, clean up new entry and continue.
			PDOWrapper::deleteByID('mods', $entryID);
		}
		
		//Mod entry in theory finished...
		
		return ResponseFactory::writeJsonData($response, [
			'identifier' => $identifier,
		]);
	}
}
