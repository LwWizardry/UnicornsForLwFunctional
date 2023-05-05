<?php

namespace MP;

use MP\DbEntries\LoginChallenge;
use MP\DbEntries\LWUser;
use MP\DbEntries\User;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\Helpers\UniqueInjectorHelper;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

class LoginManager {
	public static function finalizeLogin(Response $response, LoginChallenge $loginChallenge): Response {
		$lwAuthor = $loginChallenge->getAuthor();
		
		//Create query to lookup
		$statement = PDOWrapper::getPDO()->prepare('
			SELECT
				lw_users.id as lw_id,
				users.id as u_id,
				lw_users.identifier as lw_identifier,
				users.identifier as u_identifier,
				lw_users.*, users.*
			FROM lw_users
			INNER JOIN users on lw_users.user = users.id
			WHERE lw_users.identifier = :identifier OR lw_users.name = :name
		');
		
		$statement->execute([
			'identifier' => $lwAuthor->getId(),
			'name' => $lwAuthor->getUsername(),
		]);
		$result = $statement->fetchAll();
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
			$statement->execute([
				'id' => $lwAuthor->getId(),
				'name' => $lwAuthor->getUsername(),
			]);
			$result = $statement->fetchAll();
			$amount = count($result);
			if($amount !== 1) {
				//Ignoring any special error now.
				throw new InternalDescriptiveException('Failed to create LWUser, trying to login from two locations? But when trying again, there had been ' . $amount . ' users found.');
			}
			return self::loginProcessFromDBResult($response, $result, $loginChallenge->getCreatedAt());
		} else if($amount === 1) {
			//We got one result, good, this must be our user!
			return self::loginProcessFromDBResult($response, $result, $loginChallenge->getCreatedAt());
		} else if ($amount === 2) {
			throw new InternalDescriptiveException('While looking for LWAuthor using ID/Name pair, two results got returned. '
				. 'This means that the DB is somehow corrupted. This should never happen, here the colliding data: '
				. $result[0]['lw_identifier'] . ': ' . $result[0]['name'] . ' & '
				. $result[1]['lw_identifier'] . ': ' . $result[1]['name']
			);
		} else {
			throw new InternalDescriptiveException('While looking for LWAuthor ID/Name match got ' . count($result) . ' results, which should be impossible!');
		}
	}
	
	private static function loginProcessFromDBResult(Response $response, array $result, null|string $acceptedPPAt = null): Response {
		$result = $result[0];
		$lwUser = new LWUser(
			$result['lw_id'],
			$result['lw_identifier'],
			$result['name'],
			$result['picture'],
			$result['flair'],
		);
		$userToAuth = new User(
			$result['u_id'],
			$result['u_identifier'],
			$result['created_at'],
			$result['privacy_policy_accepted_at'],
		);
		if($acceptedPPAt !== null) {
			//Update the last agreed privacy policy time:
			$userToAuth->updateAcceptPPAt($acceptedPPAt);
		}
		return self::loginProcessCreateSession($response, $userToAuth, $lwUser);
	}
	
	private static function loginProcessCreateSession(Response $response, User $userToAuth, LWUser $lwUser): Response {
		//We got a valid user to auth, create session:
		$sessionID = QueryBuilder::insert('sessions')
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
		$statement = PDOWrapper::getPDO()->prepare('
			SELECT s.id, u.identifier, lu.name, lu.picture
			FROM sessions AS s
			INNER JOIN users u ON s.user = u.id
			INNER JOIN lw_users lu on u.id = lu.user
			WHERE token = :token
		');
		$statement->execute([
			'token' => $authToken,
		]);
		$result = $statement->fetchAll();
		if(count($result) !== 1) {
			return ResponseFactory::writeBadRequestError($response, 'Invalid auth token', 401);
		}
		$result = $result[0];
		
		//Update timestamp of token:
		QueryBuilder::update('sessions')
			->setUTC('last_usage_at')
			->whereValue('id', $result['id'])
			->execute();
		
		return ResponseFactory::writeJsonData($response, [
			'identifier' => $result['identifier'],
			'username' => $result['name'],
			'picture' => $result['picture'],
		]);
	}
}
