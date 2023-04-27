<?php

namespace MP\Handlers;

use MP\DbEntries\User;
use MP\Helpers\Base32;
use MP\Helpers\JsonValidator;
use MP\InternalDescriptiveException;
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
		$statement = PDOWrapper::getPDO()->prepare('
			INSERT INTO mods (title, title_sane, caption, created_at)
			VALUES (:title, :title_sane, :caption, UTC_TIMESTAMP())
			RETURNING id
		');
		try {
			$statement->execute([
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
		$entryID = $statement->fetchColumn();
		if($entryID === false) {
			throw new InternalDescriptiveException('PDO failed (false), while getting the ID of a freshly inserted mod entry.');
		}
		
		try {
			//Now that the entry is inserted, try to generate an identifier for it:
			$identifier = PDOWrapper::uniqueIdentifierInjector(
				'mods',
				'identifier',
				$entryID,
				function (): string {
					$bytes = random_bytes(5);
					return Base32::encode($bytes);
				}
			);
			if($identifier == null) {
				//Identifier was 'null', something went wrong, clean up new entry and continue.
				self::discardMod($entryID);
				throw new InternalDescriptiveException('Failed to generate unique identifier for new mod. Try again or better complain.');
			}
		} catch (PDOException $e) {
			//Limit damage, by deleting the entry:
			self::discardMod($entryID);
			throw $e;
		}
		
		//Mod entry in theory finished...
		
		return ResponseFactory::writeJsonData($response, [
			'identifier' => $identifier,
		]);
	}
	
	private static function discardMod(int $id): void {
		PDOWrapper::getPDO()->prepare('
				DELETE FROM mods
				WHERE id = :id
			')->execute([
			'id' => $id,
		]);
	}
}
