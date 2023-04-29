<?php

namespace MP\DbEntries;

use MP\LwApi\LWAuthor;
use MP\PDOWrapper;
use PDOException;

class LWUser {
	public static function tryToCreate(int $userID, LWAuthor $lwAuthor): null|LWUser {
		$statement = PDOWrapper::getPDO()->prepare('
			INSERT INTO lw_users (user, identifier, name, picture, flair)
			VALUES (:user, :identifier, :name, :picture, :flair)
			RETURNING id
		');
		try {
			$statement->execute([
				'user' => $userID,
				'identifier' => $lwAuthor->getId(),
				'name' => $lwAuthor->getUsername(),
				'picture' => $lwAuthor->getPicture(),
				'flair' => $lwAuthor->getFlair(),
			]);
		} catch (PDOException $e) {
			if(PDOWrapper::isUniqueConstrainViolation($e)) {
				return null; //Cannot create the user, as it is already created.
			}
			throw $e;
		}
		$id = $statement->fetchColumn();
		
		return new LWUser($id, $lwAuthor->getId(), $lwAuthor->getUsername(), $lwAuthor->getPicture(), $lwAuthor->getFlair());
	}
	
	private int $id;
	private int $identifier;
	private string $username;
	private null|string $picture;
	private null|string $flair;
	
	public function __construct(int $id, int $identifier, string $username, null|string $picture, null|string $flair) {
		$this->id = $id;
		$this->identifier = $identifier;
		$this->username = $username;
		$this->picture = $picture;
		$this->flair = $flair;
	}
	
	public function getId(): int {
		return $this->id;
	}
	
	public function getIdentifier(): int {
		return $this->identifier;
	}
	
	public function getUsername(): string {
		return $this->username;
	}
	
	public function getPicture(): null|string {
		return $this->picture;
	}
	
	public function getFlair(): null|string {
		return $this->flair;
	}
}