<?php

namespace MP\DbEntries;

use MP\BadRequestException;
use MP\Helpers\Base32;
use MP\InternalDescriptiveException;
use MP\PDOWrapper;

class User {
	public static function createEmpty(string $acceptedPPAt): null|User {
		$idGenerator = function (): string {
			$bytes = random_bytes(5);
			return Base32::encode($bytes);
		};
		$statement = PDOWrapper::getPDO()->prepare('
			INSERT INTO users (identifier, created_at, privacy_policy_accepted_at)
			VALUES (:identifier, UTC_TIMESTAMP(), :privacy_policy_accepted_at)
			RETURNING id, identifier, created_at
		');
		$identifierBeingUsed = PDOWrapper::uniqueInjector($statement, [
			'privacy_policy_accepted_at' => $acceptedPPAt, //No formatting, as this comes straight from the DB.
		], 'identifier', $idGenerator, true);
		if ($identifierBeingUsed === null) {
			throw new InternalDescriptiveException('Failed to generate a unique user identifier.');
		}
		
		$id = $identifierBeingUsed['id'];
		$identifier = $identifierBeingUsed['identifier'];
		$created_at = $identifierBeingUsed['created_at'];
		
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
		PDOWrapper::getPDO()->prepare('
			UPDATE sessions
			SET last_usage_at = UTC_TIMESTAMP()
			WHERE id = :id
		')->execute([
			'id' => $result['session_id'],
		]);
		
		return new User(
			$result['user_id'],
			$result['identifier'],
			$result['created_at'],
			$result['privacy_policy_accepted_at'],
		);
	}
	
	private int $id;
	private string $identifier;
	private string $createdAt;
	private string $acceptedPPAt;
	
	public function __construct(int $id, string $identifier, string $createdAt, string $acceptedPPAt) {
		$this->id = $id;
		$this->identifier = $identifier;
		$this->createdAt = $createdAt;
		$this->acceptedPPAt = $acceptedPPAt;
	}
	
	public function getId(): int {
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
	
	public function deletePrototype(): void {
		$statement = PDOWrapper::getPDO()->prepare('
			DELETE FROM users
			WHERE id = :id
		');
		$statement->execute([
			'id' => $this->id,
		]);
	}
	
	public function updateAcceptPPAt(string $acceptedPPAt): void {
		PDOWrapper::getPDO()->prepare('
				UPDATE users
				SET privacy_policy_accepted_at = :newTime1
				WHERE id = :id AND privacy_policy_accepted_at < :newTime2
			')->execute([
			'id' => $this->id,
			//TBI: Yes, this kind of makes sense, but is there a better way?
			'newTime1' => $acceptedPPAt,
			'newTime2' => $acceptedPPAt,
		]);
	}
}
