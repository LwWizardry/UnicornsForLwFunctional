<?php

namespace MP\Handlers;

use MP\ResponseFactory;
use MP\SlimSetup;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class HandlerList {
	public static function initializeRouteHandlers(): void {
		LoginHandler::initializeRouteHandlers();
		
		SlimSetup::getSlim()->get('/mods', function (Request $request, Response $response) {
			$modList = [];
			$modList[] = [
				"name" => "CustomWirePlacer",
			];
			$modList[] = [
				"name" => "AssemblyLoader",
			];
			$modList[] = [
				"name" => "HarmonyForLogicWorld",
			];
			return ResponseFactory::writeJson($response, $modList);
		});
	}
}
