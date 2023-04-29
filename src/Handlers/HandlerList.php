<?php

namespace MP\Handlers;

use MP\PDOWrapper;
use MP\ResponseFactory;
use MP\SlimSetup;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HandlerList {
	public static function initializeRouteHandlers(): void {
		LoginHandler::initializeRouteHandlers();
		EditModHandler::initializeRouteHandlers();
		
		SlimSetup::getSlim()->get('/mods', function (Request $request, Response $response) {
			$statement = PDOWrapper::getPDO()->prepare('
				SELECT title, caption, identifier
				FROM mods;
			');
			$statement->execute();
			$result = $statement->fetchAll();
			
			$modList = [];
			foreach ($result as $modEntry) {
				$modList[] = [
					'identifier' => $modEntry['identifier'],
					'title' => $modEntry['title'],
					'caption' => $modEntry['caption'],
					'logo' => null,
				];
			}
			return ResponseFactory::writeJsonData($response, $modList);
		});
	}
}
