<?php

namespace MP\Handlers;

use MP\DbEntries\LoginChallenge;
use MP\LoginManager;
use MP\LwApi\LWBackend;
use MP\ResponseFactory;
use MP\SlimSetup;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class LoginHandler {
	public static function initializeRouteHandlers(): void {
		SlimSetup::getSlim()->get('/auth/login/new', self::onNewToken(...));
		SlimSetup::getSlim()->get('/auth/login/created', self::onCommentCreated(...));
		SlimSetup::getSlim()->get('/auth/login/deleted', self::onCommentsDeleted(...));
		SlimSetup::getSlim()->get('/auth/login/probe', self::onLoginProbe(...));
	}
	
	private static function onNewToken(Request $request, Response $response): Response {
		//Validate, that the user agrees to the 'privacy policy':
		$parameter = $request->getQueryParams();
		if(!isset($parameter['privacy-policy'])) {
			return ResponseFactory::writeBadRequestError($response, 'Missing privacy policy statement');
		}
		if($parameter['privacy-policy'] != 'accept') {
			return ResponseFactory::writeBadRequestError($response, 'Called API with the intention of not accepting privacy policy');
		}
		
		//As initial step, remove all challenge entries that have expired:
		LoginChallenge::deleteOutdated();
		
		//TBI: Add challenge test against existing comments,
		// to ensure that the challenge won't already be fulfilled.
		$loginChallenge = LoginChallenge::generateNewChallenge();
		return ResponseFactory::writeJsonData($response, [
			'challenge' => $loginChallenge->getChallenge(),
			'session' => $loginChallenge->getSession(),
		]);
	}
	
	private static function onCommentCreated(Request $request, Response $response): Response {
		$sessionID = SlimSetup::expectAuthorizationHeader($request);
		$loginChallenge = LoginChallenge::getChallengeForSession($sessionID);
		if ($loginChallenge === null) {
			//Could not find the session ID in DB (or its entry is corrupted) => Let client acquire a new one.
			return ResponseFactory::writeFailureMessageActions($response, 'Unknown or expired login session ID', [
				['action' => 'new-session'],
			]);
		}
		//Found a valid session for request!
		
		//Query every comment from the LW-Website's Forums authentication thread:
		$comments = LWBackend::queryCommentsForPost('pst-3c6860ea');
		
		//Filter all comments, by if they contain the challenge or not:
		$commentsMatchingBody = [];
		$commentsMatchingBodyEdited = []; //Also remember the invalid ones.
		foreach ($comments as $comment) {
			if ($comment->getBody() == $loginChallenge->getChallenge()) {
				if($comment->isEdited()) {
					$commentsMatchingBodyEdited[] = $comment;
				} else {
					$commentsMatchingBody[] = $comment;
				}
			}
		}
		
		if(count($commentsMatchingBody) == 0) {
			//No unedited message with challenge found. Report to user!
			if(count($commentsMatchingBodyEdited) != 0) {
				return ResponseFactory::writeFailureMessage($response,
					'WARNING: Your comment with the challenge may not be edited'
					. ' you can simply create a new comment with the challenge.' . PHP_EOL
					. ' Or safer reload the page to get a new challenge.'
				);
			}
			return ResponseFactory::writeFailureMessage($response, 'Challenge comment not yet set!');
		}
		//At this point it is the users responsibility to make sure, that it created the message and did not edit it.
		// Random "ensures", that no other user has created a comment with the same challenge.
		// If someone copies the message, it must happen after the message was sent by the original author.
		// Deleting or editing it is careless and must never be done.
		
		//Hence, take all unedited messages, sort them by creation, to find the original author to link.
		// The latter on deleting messages, confirms that there is control over the account to link.
		// Or something really likes to give his own account away to someone else that was lucky enough to choose that challenge.
		
		//Find the oldest/first message containing the challenge:
		$earliest_comment = $commentsMatchingBody[0];
		for($i = 1; $i < count($commentsMatchingBody); $i++) {
			$comment = $commentsMatchingBody[$i];
			if($comment->getCreatedAt() < $earliest_comment->getCreatedAt()) {
				$earliest_comment = $comment;
			}
		}
		//Found the oldest not edited comment, containing the challenge.
		// Can't help it, if challenge gets copied by someone else and the original gets deleted - that's on the user then.
		
		$relevantAuthor = $earliest_comment->getAuthor();
		$loginChallenge->updateWithAuthor($relevantAuthor); //Saves the author to DB.
		
		//Collect all messages by linking author:
		$allMessagesToDelete = [];
		foreach ($comments as $comment) {
			if ($comment->getAuthor()->getId() == $relevantAuthor->getId()) {
				$allMessagesToDelete[] = [
					'id' => $comment->getId(),
					'content' => $comment->getBody(),
				];
			}
		}
		
		return ResponseFactory::writeJsonData($response, [
			'author' => $relevantAuthor->getUsername(),
			'messagesToDelete' => $allMessagesToDelete,
		]);
	}
	
	private static function onCommentsDeleted(Request $request, Response $response): Response {
		$sessionID = SlimSetup::expectAuthorizationHeader($request);
		$loginChallenge = LoginChallenge::getChallengeForSession($sessionID);
		if ($loginChallenge === null) {
			//Could not find the session ID in DB (or its entry is corrupted) => Let client acquire a new one.
			return ResponseFactory::writeFailureMessageActions($response, 'Unknown or expired login session ID', [
				['action' => 'new-session'],
			]);
		}
		//Found a valid session for request!
		
		if (!$loginChallenge->hasAuthor()) {
			//There is no author (properly) registered for this challenge, use is one step ahead!
			return ResponseFactory::writeBadRequestError($response, 'Called /deleted API route, while not yet passing the /created API route. This should never happen.');
		}
		
		$comments = LWBackend::queryCommentsForPost('pst-3c6860ea');
		$commentsMatchingBody = [];
		foreach ($comments as $comment) {
			if ($comment->getAuthor()->getId() == $loginChallenge->getAuthor()->getId()) {
				$commentsMatchingBody[] = [
					'id' => $comment->getId(),
					'content' => $comment->getBody(),
				];
			}
		}
		
		if (count($commentsMatchingBody) != 0) {
			return ResponseFactory::writeFailureMessageActions($response, 'There are still comments to delete.', [
				[
					'action' => 'update-comments',
					'comments' => $commentsMatchingBody,
				]
			]);
		}
		
		$response = LoginManager::finalizeLogin($response, $loginChallenge);
		//We got the response for the user, clean up by deleting the challenge:
		$loginChallenge->delete();
		return $response;
	}
	
	private static function onLoginProbe(Request $request, Response $response): Response {
		$authToken = SlimSetup::expectAuthorizationHeader($request);
		return LoginManager::isLoggedIn($response, $authToken);
	}
}
