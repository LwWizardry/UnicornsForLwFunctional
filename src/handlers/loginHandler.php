<?php

use MP\LWBackend;
use MP\SlimSetup;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

SlimSetup::getSlim()->get('/auth/login/new', function (Request $request, Response $response, array $args) {
	$serverChallenge = [
		"challenge" => "Super mega nice challenge with ID 52351243 that will do for the 754743's test!",
		"session" => "912475621398",
	];
	$response->getBody()->write(json_encode($serverChallenge));
	return $response;
});

SlimSetup::getSlim()->get('/auth/login/created', function (Request $request, Response $response, array $args) {
	if (!isset($_GET['session'])) {
		//The session parameter is incomplete.
		return $response->withStatus(401, 'Missing session ID');
	}
	$sessionID = $_GET['session'];
	//TODO: Actually look up the session ID in the DB - as one has to get the challenge from that entry
	if ($sessionID != '912475621398') {
		//If exists in DB & timeout => remove & timeout
		//If does not exist in DB => missing
		//Either way: Refresh on client!
		return $response->withStatus(400, 'Unknown session ID');
	}
	$challenge = 'Super mega nice challenge with ID 52351243 that will do for the 754743\'s test!';
	
	$comments = LWBackend::queryCommentsForPost('pst-3c6860ea');
	if ($comments->hasFailed()) {
		$response->getBody()->write(json_encode([
			'ok' => false,
			'reason' => 'Failed to get content: ' . $comments->getContent(),
		]));
		return $response->withStatus(500, $comments->getContent());
	}
	$commentsWithValidChallenge = [];
	//We got valid comments:
	foreach ($comments->getContent() as $commentUnTyped) {
		$comment = (fn($obj): MP\LWObjects\LWComment => $obj)($commentUnTyped);
		if($comment->getBody() == $challenge) {
			$commentsWithValidChallenge[] = $comment;
		}
	}
	
	$amountOfValidChallengeComments = count($commentsWithValidChallenge);
	if($amountOfValidChallengeComments == 0) {
		//No challenge...
		$response->getBody()->write(json_encode([
			'ok' => false,
			'reason' => 'Challenge comment not yet set!',
		]));
		return $response->withStatus(200, 'But no matching comment');
	}
	else if($amountOfValidChallengeComments > 1) {
		//Too many challenges, validate that everyone is from the same user...
		$id = $commentsWithValidChallenge[0]->getAuthor()->getId();
		for($i = 1; $i < $amountOfValidChallengeComments; $i++) {
			if($commentsWithValidChallenge[$i]->getAuthor()->getId() != $id) {
				//We found another user that sent the exact same challenge, ERROR!
				//TODO: Return issue.
				$response->getBody()->write(json_encode([
					'ok' => false,
					'reason' => 'Multiple users used challenge, refresh!',
				]));
				return $response->withStatus(200, 'But multiple users used challenge');
			}
		}
	}
	
	$relevantAuthor = $commentsWithValidChallenge[0]->getAuthor();
	$allMessagesToDelete = [];
	foreach ($comments->getContent() as $commentUntyped) {
		$comment = (fn($obj): MP\LWObjects\LWComment => $obj)($commentUntyped);
		if($comment->getAuthor()->getId() == $relevantAuthor->getId()) {
			$allMessagesToDelete[] = [
				'id' => $comment->getId(),
				'content' => $comment->getBody(),
			];
		}
	}
	
	$response->getBody()->write(json_encode([
		'ok' => true,
		'messagesToDelete' => $allMessagesToDelete,
	]));
	return $response;
});
