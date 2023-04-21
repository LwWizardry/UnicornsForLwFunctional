<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require __DIR__ . '/../../vendor/autoload.php';

use MP\SlimSetup;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

//Load environment variables:
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();
$dotenv->required(['DB_NAME', 'DB_USER', 'DB_PASSWORD', 'PRODUCTION_FRONTEND_URL'])->notEmpty();

SlimSetup::setup();
//Any error above this point will result in a CORS issue in the browser. Mostly these are failed setup errors though.

SlimSetup::getSlim()->get('/mod/{name}[/]', function (Request $request, Response $response, array $args) {
	$mod_name = $args['name'];
	if(preg_match("/^[A-Za-z0-9_-]+$/", $mod_name) !== 1) {
		//Invalid mod name, refuse the request gracefully.
		$title = 'Invalid mod name';
		
		$search_title = '404 Nothing here';
		$search_description = 'Mod does not exist as it has an invalid name.';
		
		$url_title = 'List of mods';
		$destination_url = '/mods';
	} else {
		//TODO: Actually query DB. If mod does not exist, just forward anyway.
		$title = 'Mod: ' . $mod_name;
		
		$search_title = $title;
		$search_description = 'An epic mod for every purpose!';
		
		$url_title = $title;
		$destination_url = '/mod-direct/' . $mod_name;
	}
	
	//Old forwarding: <!--<script>window.location.href = "$destination_url";</script>-->
	// Bad as it adds a browser history entry...
	$response->getBody()->write(
		<<<HTML
		<!DOCTYPE html>
		<head>
			<meta charset="UTF-8">
			<title>$title</title>
			<meta property="og:title" content="$search_title">
			<meta property="og:description" content="$search_description">
			<script>document.location.replace("$destination_url");</script>
			<style>body{background-color:#181818; color:#9f9f9f} a{color:#00bd7e}</style>
		</head>
		<body>
			<p>$title</p>
			<p>
				In case that you do not get redirected automatically click here:
				<a href="$destination_url">$url_title</a>
				(Report a bug, unless you disabled JS).
			</p>
		</body>
		</html>
		HTML);
	return $response;
});

SlimSetup::run();
