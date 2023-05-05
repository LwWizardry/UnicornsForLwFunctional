<?php

namespace MP\DbEntries;

use MP\ErrorHandling\BadRequestException;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\Helpers\UniqueInjectorHelper;
use MP\PDOWrapper;
use Throwable;

class User {
	public static function createEmpty(string $acceptedPPAt): null|User {
		$result = QueryBuilder::insert('users')
			->setUTC('created_at')
			->setValue('privacy_policy_accepted_at', $acceptedPPAt)
			->return('id', 'created_at')
			->execute();
		$id = $result['id'];
		$created_at = $result['created_at'];
		
		try {
			$identifier = UniqueInjectorHelper::shortIdentifier('users', $id);
		} catch (Throwable $e) {
			PDOWrapper::deleteByIDSafe('users', $id);
			throw $e;
		}
		
		return new User($id, $identifier, $created_at, $acceptedPPAt);
	}
	
	public static function fromSession($authToken): self {
		$statement = PDOWrapper::getPDO()->prepare('
			SELECT s.id AS session_id, u.id AS user_id, u.identifier, u.created_at, u.privacy_policy_accepted_at
			FROM sessions AS s
			INNER JOIN users u ON s.user = u.id
			WHERE token = :token
		');
		$statement->execute([
			'token' => $authToken,
		]);
		$result = $statement->fetchAll();
		if(count($result) !== 1) {
			throw new BadRequestException('Invalid auth token');
		}
		$result = $result[0];
		
		//Update timestamp of token:
		QueryBuilder::update('sessions')
			->setUTC('last_usage_at')
			->whereValue('id', $result['session_id'])
			->execute();
		
		return new User(
			$result['user_id'],
			$result['identifier'],
			$result['created_at'],
			$result['privacy_policy_accepted_at'],
		);
	}
	
	public static function fromIdentifier(string $identifier): null|User {
		$result = QueryBuilder::select('users')
			->whereValue('identifier', $identifier)
			->execute(true);
		if($result === false) {
			return null;
		}
		
		return new User(
			$result['id'],
			$result['identifier'],
			$result['created_at'],
			$result['privacy_policy_accepted_at'],
		);
	}
	
	private int $id;
	private string $identifier;
	private string $createdAt;
	private string $acceptedPPAt;
	
	public function __construct(int $id, null|string $identifier, string $createdAt, string $acceptedPPAt) {
		$this->id = $id;
		//TBI: Check somewhere else or in the constructors of this object?
		if($identifier === null) {
			//Whoops something went heavily wrong in the setup of this user!
			throw new InternalDescriptiveException('Attempted to create user object with "null" identifier!');
		}
		$this->identifier = $identifier;
		$this->createdAt = $createdAt;
		$this->acceptedPPAt = $acceptedPPAt;
	}
	
	public function getDbId(): int {
		return $this->id;
	}
	
	public function getIdentifier(): string {
		return $this->identifier;
	}
	
	public function getCreatedAt(): string {
		return $this->createdAt;
	}
	
	public function getAcceptedPPAt(): string {
		return $this->acceptedPPAt;
	}
	
	public function deletePrototype(bool $safe = false): void {
		if($safe) {
			PDOWrapper::deleteByIDSafe('users', $this->id);
		} else {
			PDOWrapper::deleteByID('users', $this->id);
		}
	}
	
	public function updateAcceptPPAt(string $acceptedPPAt): void {
		QueryBuilder::update('users')
			->setValue('privacy_policy_accepted_at', $acceptedPPAt)
			->whereValue('id', $this->id)
			->whereValue('privacy_policy_accepted_at', $acceptedPPAt, '<')
			->execute();
	}
}
