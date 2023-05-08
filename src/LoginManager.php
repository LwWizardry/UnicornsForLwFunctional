<?php

namespace MP;

use MP\DatabaseTables\TableLoginChallenge;
use MP\DatabaseTables\TableLWUser;
use MP\DatabaseTables\TableUser;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\QueryBuilder as QB;
use MP\Helpers\UniqueInjectorHelper;
use PDOException;
use Psr\Http\Message\ResponseInterface as Response;
use Throwable;

class LoginManager {
	public static function finalizeLogin(Response $response, TableLoginChallenge $loginChallenge): Response {
		$lwAuthor = $loginChallenge->getAuthor();
		
		//Create query to lookup
		$query = TableUser::getBuilder(privateData: true)
			->joinThat('user', TableLWUser::getBuilder(otherData: true)
				->whereValue('identifier', $lwAuthor->getId())
				->whereValue('name', $lwAuthor->getUsername())
				, type: 'LEFT')
			->whereType('OR')
			->build();
		$result = $query->execute();
		$amount = count($result);
		
		if($amount === 0) {
			//Attempt to create a user:
			$userToAuth = TableUser::createEmpty($loginChallenge->getCreatedAt());
			//With user created, try to create link to LW-User - if this fails, rollback the new user.
			try {
				$lwUser = TableLWUser::tryToCreate($userToAuth->getDbId(), $lwAuthor);
			} catch (PDOException $e) {
				$userToAuth->deletePrototype(true); //Clean up!
				throw $e;
			}
			if($lwUser !== null) {
				//Great, the user got created without issues, parse it and continue:
				$userToAuth->injectLinkage($lwUser);
				return self::loginProcessCreateSession($response, $userToAuth);
			}
			//Failed to create LW user, because it probably was created at the same time.
			$userToAuth->deletePrototype(); //Clean up!
			
			//Try again to fetch exactly 1 user:
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
		$userToAuth = TableUser::fromDB($result, privateData: true, fetchLinkage: true);
		if($acceptedPPAt !== null) {
			//Update the last agreed privacy policy time:
			$userToAuth->updateAcceptPPAt($acceptedPPAt);
		}
		return self::loginProcessCreateSession($response, $userToAuth);
	}
	
	private static function loginProcessCreateSession(Response $response, TableUser $userToAuth): Response {
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
		$lwLinkage = $userToAuth->getLwLinkageNonNull();
		return ResponseFactory::writeJsonData($response, [
			//Used in API requests:
			'token' => $token,
			'identifier' => $userToAuth->getIdentifier(),
			//Purely visual usage:
			'username' => $lwLinkage->getName(),
			'picture' => $lwLinkage->getPicture(),
		]);
	}
	
	public static function isLoggedIn(Response $response, string $authToken): Response {
		$result = TableUser::getBuilder(fetchUsername: true)
			->joinThat('user', QB::select('sessions')
				->selectColumn('id')
				->whereValue('token', $authToken))
			->expectOneRow()
			->execute();
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
