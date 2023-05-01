<?php

namespace MP\Handlers;

use MP\DbEntries\ModDetails;
use MP\DbEntries\ModSummary;
use MP\PDOWrapper;
use MP\ResponseFactory;
use MP\SlimSetup;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HandlerList {
	public static function initializeRouteHandlers(): void {
		LoginHandler::initializeRouteHandlers();
		EditModHandler::initializeRouteHandlers();
		
		SlimSetup::getSlim()->get('/mods', self::addMod(...));
		SlimSetup::getSlim()->get('/mod-details', self::modDetails(...));
	}
	
	public static function addMod(Request $request, Response $response): Response {
		$statement = PDOWrapper::getPDO()->prepare('
				SELECT m.title, m.caption, m.identifier,
				       u.identifier as u_identifier,
				       lu.name as lw_name, lu.identifier as lw_id, lu.picture as lw_picture
				FROM mods m
				INNER JOIN users u ON m.owner = u.id
				LEFT JOIN lw_users lu on lu.user = u.id
			');
		$statement->execute();
		$result = $statement->fetchAll();
		
		$modList = [];
		foreach ($result as $modEntry) {
			//If the name or the ID is NULL, this entry is invalid and should not exist in the first place.
			$lw_user = $modEntry['lw_name'] === null || $modEntry['lw_id'] === null ? null : [
				'id' => $modEntry['lw_id'],
				'name' => $modEntry['lw_name'],
				'picture' => $modEntry['lw_picture'],
			];
			$modList[] = [
				'identifier' => $modEntry['identifier'],
				'title' => $modEntry['title'],
				'caption' => $modEntry['caption'],
				'owner' => [
					'identifier' => $modEntry['u_identifier'],
					'lw_data' => $lw_user,
				],
				'logo' => null,
			];
		}
		return ResponseFactory::writeJsonData($response, $modList);
	}
	
	public static function modDetails(Request $request, Response $response): Response {
		$params = $request->getQueryParams();
		if(!isset($params['identifier'])) {
			return ResponseFactory::writeBadRequestError($response, 'Missing "identifier" query in URL.');
		}
		$identifier = $params['identifier'];
		if(empty($identifier)) {
			return ResponseFactory::writeBadRequestError($response, 'Missing "identifier" query value in URL.');
		}
		
		if(!str_starts_with($identifier, 'mod-')) {
			return ResponseFactory::writeBadRequestError($response, 'Invalid mod identifier provided.');
		}
		$identifier = substr($identifier, 4);
		
		//Got valid request, query data from DB:
		$modDetails = ModDetails::getModFromIdentifier($identifier);
		return ResponseFactory::writeJsonData($response, [
			'details' => $modDetails?->asFrontEndJSON(),
		]);
	}
}
