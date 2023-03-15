<?php

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

require_once 'loginHandler.php';

$app->get('/a', function (Request $request, Response $response, $args) {
	$response->getBody()->write("No u 2!");
	return $response;
});

$app->get('/mods', function (Request $request, Response $response, $args) {
	$modList = array();
	$modList[] = array(
		"name" => "CustomWirePlacer"
	);
	$modList[] = array(
		"name" => "AssemblyLoader"
	);
	$modList[] = array(
		"name" => "HarmonyForLogicWorld"
	);
	
	//$response->getBody()->write('<pre>' . json_encode($modList, JSON_PRETTY_PRINT) . '</pre>');
	$response->getBody()->write(json_encode($modList));
	return $response;
});
