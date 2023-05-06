<?php

namespace MP;

use MP\DbEntries\LoginChallenge;
use MP\DbEntries\LWUser;
use MP\DbEntries\User;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\QueryBuilder as QB;
use MP\Helpers\UniqueInjectorHelper;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

class LoginManager {
	public static function finalizeLogin(Response $response, LoginChallenge $loginChallenge): Response {
		$lwAuthor = $loginChallenge->getAuthor();
		
		//Create query to lookup
		$query = QB::select('lw_users')
			->selectColumn('id', 'identifier', 'name', 'picture', 'flair')
			->join(QB::select('users')
				->selectColumn('id', 'identifier', 'created_at', 'privacy_policy_accepted_at'),
			thisColumn: 'user')
			->whereType('OR')
			->whereValue('identifier', $lwAuthor->getId())
			->whereValue('name', $lwAuthor->getUsername());
		$result = $query->execute();
		$amount = count($result);
		
		if($amount === 0) {
			//Attempt to create a user:
			$userToAuth = User::createEmpty($loginChallenge->getCreatedAt());
			//With user created, try to create link to LW-User - if this fails, rollback the new user.
			try {
				$lwUser = LWUser::tryToCreate($userToAuth->getDbId(), $lwAuthor);
			} catch (PDOException $e) {
				$userToAuth->deletePrototype(true); //Clean up!
				throw $e;
			}
			if($lwUser !== null) {
				//Great, the user got created without issues, parse it and continue:
				return self::loginProcessCreateSession($response, $userToAuth, $lwUser);
			}
			//Failed to create LW user, because it probably was created at the same time.
			$userToAuth->deletePrototype(); //Clean up!
			
			//Try again to fetch exactly 1 user:
			//TODO: This builds the query a second time, cache that!
			$result = $query->execute();
			$amount = count($result);
			if($amount !== 1) {
				//Ignoring any special error now.
				throw new InternalDescriptiveException('Failed to create LWUser, trying to login from two locations? But when trying again, there had been ' . $amount . ' users found.');
			}
			return self::loginProcessFromDBResult($response, $result[0], $loginChallenge->getCreatedAt());
		} else if($amount === 1) {
			//We got one result, good, this must be our user!
			return self::loginProcessFromDBResult($response, $result[0], $loginChallenge->getCreatedAt());
		} else if ($amount === 2) {
			throw new InternalDescriptiveException('While looking for LWAuthor using ID/Name pair, two results got returned. '
				. 'This means that the DB is somehow corrupted. This should never happen, here the colliding data: '
				. $result[0]['lw_users.identifier'] . ': ' . $result[0]['lw_users.name'] . ' & '
				. $result[1]['lw_users.identifier'] . ': ' . $result[1]['lw_users.name']
			);
		} else {
			throw new InternalDescriptiveException('While looking for LWAuthor ID/Name match got ' . count($result) . ' results, which should be impossible!');
		}
	}
	
	private static function loginProcessFromDBResult(Response $response, array $result, null|string $acceptedPPAt = null): Response {
		$lwUser = new LWUser(
			$result['lw_users.id'],
			$result['lw_users.identifier'],
			$result['lw_users.name'],
			$result['lw_users.picture'],
			$result['lw_users.flair'],
		);
		$userToAuth = new User(
			$result['users.id'],
			$result['users.identifier'],
			$result['users.created_at'],
			$result['users.privacy_policy_accepted_at'],
		);
		if($acceptedPPAt !== null) {
			//Update the last agreed privacy policy time:
			$userToAuth->updateAcceptPPAt($acceptedPPAt);
		}
		return self::loginProcessCreateSession($response, $userToAuth, $lwUser);
	}
	
	private static function loginProcessCreateSession(Response $response, User $userToAuth, LWUser $lwUser): Response {
		//We got a valid user to auth, create session:
		$sessionID = QB::insert('sessions')
			->setUTC('issued_at')
			->setUTC('last_usage_at')
			->setValue('user', $userToAuth->getDbId())
			->return('id')
			->execute();
		try {
			$token = UniqueInjectorHelper::largeIdentifier('sessions', $sessionID, 'token');
		} catch (Throwable $e) {
			PDOWrapper::deleteByIDSafe('sessions', $sessionID);
			throw $e;
		}
		
		//Return valid session:
		return ResponseFactory::writeJsonData($response, [
			//Used in API requests:
			'token' => $token,
			'identifier' => $userToAuth->getIdentifier(),
			//Purely visual usage:
			'username' => $lwUser->getUsername(),
			'picture' => $lwUser->getPicture(),
		]);
	}
	
	public static function isLoggedIn(Response $response, string $authToken): Response {
		$result = QB::select('sessions')
			->selectColumn('id')
			->join(QB::select('users')
				->selectColumn('identifier')
				->join(QB::select('lw_users')
					->selectColumn('name', 'picture'),
				thatColumn: 'user'),
			thisColumn: 'user')
			->whereValue('token', $authToken)
			->execute(true);
		if($result === false) {
			return ResponseFactory::writeBadRequestError($response, 'Invalid auth token', 401);
		}
		
		//Update timestamp of token:
		QB::update('sessions')
			->setUTC('last_usage_at')
			->whereValue('id', $result['sessions.id'])
			->execute();
		
		return ResponseFactory::writeJsonData($response, [
			'identifier' => $result['users.identifier'],
			'username' => $result['lw_users.name'],
			'picture' => $result['lw_users.picture'],
		]);
	}
}
