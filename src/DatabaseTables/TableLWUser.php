<?php

namespace MP\DatabaseTables;

use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\LwApi\LWAuthor;
use MP\PDOWrapper;
use PDOException;

class TableLWUser {
	public static function tryToCreate(int $userID, LWAuthor $lwAuthor): null|TableLWUser {
		try {
			$id = QueryBuilder::insert('lw_users')
				->setValues([
					'user' => $userID,
					'identifier' => $lwAuthor->getId(),
					'name' => $lwAuthor->getUsername(),
					'picture' => $lwAuthor->getPicture(),
					'flair' => $lwAuthor->getFlair(),
				])
				->return('id')
				->execute();
		} catch (PDOException $e) {
			if(PDOWrapper::isUniqueConstrainViolation($e)) {
				return null; //Cannot create the user, as it is already created.
			}
			throw $e;
		}
		
		return new TableLWUser($id, $lwAuthor->getId(), $lwAuthor->getUsername(), $lwAuthor->getPicture(), $lwAuthor->getFlair());
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
	
	public function getDbId(): int {
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