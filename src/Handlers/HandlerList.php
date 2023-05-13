<?php

namespace MP\Handlers;

use MP\DatabaseTables\TableModDetails;
use MP\DatabaseTables\TableModSummary;
use MP\ResponseFactory;
use MP\SlimSetup;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HandlerList {
	public static function initializeRouteHandlers(): void {
		LoginHandler::initializeRouteHandlers();
		EditModHandler::initializeRouteHandlers();
		AssetHandler::initializeRouteHandlers();
		
		SlimSetup::getSlim()->get('/mods', self::listMods(...));
		SlimSetup::getSlim()->get('/mod-details', self::modDetails(...));
	}
	
	public static function listMods(Request $request, Response $response): Response {
		$result = TableModSummary::getBuilder(fetchUser: true)->execute();
		
		$modList = [];
		foreach ($result as $modEntry) {
			$mod = TableModSummary::fromDB($modEntry, fetchUsername: true);
			$modList[] = $mod->asFrontEndJSON();
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
		$modDetails = TableModDetails::getModFromIdentifier($identifier);
		return ResponseFactory::writeJsonData($response, [
			'details' => $modDetails?->asFrontEndJSON(),
		]);
	}
}
