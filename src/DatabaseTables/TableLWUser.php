<?php

namespace MP\DatabaseTables;

use Exception;
use MP\DatabaseTables\Generic\Fetchable;
use MP\Helpers\QueryBuilder\Queries\SelectBuilder;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\Helpers\QueryBuilder\QueryBuilder as QB;
use MP\LwApi\LWAuthor;
use MP\PDOWrapper;
use PDOException;

class TableLWUser {
	public static function tryToCreate(int $userID, LWAuthor $lwAuthor): null|self {
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
		
		return new self($id, $lwAuthor->getUsername(), $lwAuthor->getPicture(), $lwAuthor->getId(), $lwAuthor->getFlair());
	}
	
	public static function getBuilder(bool $otherData = false): SelectBuilder {
		$query = QB::select('lw_users', 'pLWU')
			//Identifier is always fetched, to ensure data is valid on parsing.
			->selectColumn('id', 'identifier', 'name', 'picture');
		if($otherData) {
			$query->selectColumn('flair');
		}
		return $query;
	}
	
	public static function fromDB(
		array $columns, string $prefix = 'lw_users.',
		bool $otherData = false
	): null|self {
		if($columns[$prefix . 'identifier'] === null || $columns[$prefix . 'name'] === null) {
			return null;
		}
		return new self(
			$columns[$prefix. 'id'],
			$columns[$prefix. 'name'],
			$columns[$prefix. 'picture'],
			//As identifier is always fetched anyway, it might as well be always set:
			$columns[$prefix. 'identifier'],
			$otherData ? $columns[$prefix. 'flair'] : Fetchable::i(),
		);
	}
	
	private int $dbID;
	private string $name;
	private null|string $picture;
	private int $identifier;
	private Fetchable|null|string $flair;
	
	private function __construct(int $dbID, string $name, null|string $picture, int $identifier, Fetchable|null|string $flair) {
		$this->dbID = $dbID;
		$this->name = $name;
		$this->picture = $picture;
		$this->identifier = $identifier;
		$this->flair = $flair;
	}
	
	/**
	 * @return int
	 */
	public function getDbID(): int {
		return $this->dbID;
	}
	
	/**
	 * @return string
	 */
	public function getName(): string {
		return $this->name;
	}
	
	/**
	 * @return null|string
	 */
	public function getPicture(): null|string {
		return $this->picture;
	}
	
	/**
	 * @return null|string
	 */
	public function getFlair(): string|null {
		if(Fetchable::isFetchable($this->flair)) {
			throw new Exception('Tried to get get fetchable lw_user flair, but it was not set yet.');
		}
		return $this->flair;
	}
	
	public function asFrontEndJSON(): array {
		return [
			//TBI: ID is actually not yet needed on the frontend.
			'id' => $this->identifier,
			'name' => $this->name,
			'picture' => $this->picture,
		];
	}
}
