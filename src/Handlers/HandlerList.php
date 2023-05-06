<?php

namespace MP\Handlers;

use MP\DbEntries\ModDetails;
use MP\Helpers\QueryBuilder\QueryBuilder as QB;
use MP\ResponseFactory;
use MP\SlimSetup;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HandlerList {
	public static function initializeRouteHandlers(): void {
		LoginHandler::initializeRouteHandlers();
		EditModHandler::initializeRouteHandlers();
		
		SlimSetup::getSlim()->get('/mods', self::listMods(...));
		SlimSetup::getSlim()->get('/mod-details', self::modDetails(...));
	}
	
	public static function listMods(Request $request, Response $response): Response {
		$result = QB::select('users')
			->selectColumn('identifier')
			->join(QB::select('mods')
				->selectColumn('title', 'caption', 'identifier'),
			thisColumn: 'owner')
			->join(QB::select('lw_users')
				->selectColumn('name', 'identifier', 'picture'),
			thatColumn: 'user', optional: true)
			->execute();
		
		$modList = [];
		foreach ($result as $modEntry) {
			//If the name or the ID is NULL, this entry is invalid and should not exist in the first place.
			$lw_user = $modEntry['lw_users.name'] === null || $modEntry['lw_users.identifier'] === null ? null : [
				'id' => $modEntry['lw_users.identifier'],
				'name' => $modEntry['lw_users.name'],
				'picture' => $modEntry['lw_users.picture'],
			];
			$modList[] = [
				'identifier' => $modEntry['mods.identifier'],
				'title' => $modEntry['mods.title'],
				'caption' => $modEntry['mods.caption'],
				'owner' => [
					'identifier' => $modEntry['users.identifier'],
					'lw_data' => $lw_user,
				],
				'logo' => null,
			];
		}
		return ResponseFactory::writeJsonData($response, $modList);
	}
	
	public static function modDetails(Request $request, Response $response): Response {
		$params = $request->getQueryParams();
		if(!array_key_exists('identifier', $params)) {
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
