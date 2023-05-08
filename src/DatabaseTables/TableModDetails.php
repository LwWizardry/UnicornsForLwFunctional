<?php

namespace MP\DatabaseTables;

use MP\DatabaseTables\Generic\Fetchable;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\Queries\SelectBuilder;
use MP\Helpers\QueryBuilder\QueryBuilder as QB;
use MP\Helpers\UniqueInjectorHelper;
use MP\PDOWrapper;
use PDOException;

class TableModDetails {
	public static function getModFromIdentifier(string $identifier): null|self {
		$result = TableUser::getBuilder(fetchUsername: true)
			->joinThat('owner', self::getBuilder()
				->whereValue('identifier', $identifier))
			->expectOneRow()
			->execute();
		if($result === false) {
			return null;
		}
		return self::fromDB($result);
	}
	
	public static function addNewMod(string $title, string $caption, TableUser $user): null|self {
		//TODO: Improve title validation, remove "'" and other funny characters.
		// Can probably be expanded on demand.
		//Sanitise title, by making it lowercase:
		$title_sane = mb_strtolower($title);
		
		//Inject a mod entry into DB, Title/title_normalized/Caption - Warning: Title_sane can be a duplicate!
		try {
			$result = QB::insert('mods')
				->setValues([
					'title' => $title,
					'title_sane' => $title_sane,
					'caption' => $caption,
					'owner' => $user->getDbId(),
				])
				->setUTC('created_at')
				->return('id', 'created_at')
				->execute();
		} catch (PDOException $e) {
			if(PDOWrapper::isUniqueConstrainViolation($e)) {
				return null;
			}
			throw $e;
		}
		$entryID = $result['id'];
		$createdAt = $result['created_at'];
		
		try {
			//Now that the entry is inserted, try to generate an identifier for it:
			$identifier = UniqueInjectorHelper::shortIdentifier('mods', $entryID);
		} catch (PDOException $e) {
			//Limit damage, by deleting the entry:
			PDOWrapper::deleteByIDSafe('mods', $entryID);
			throw $e;
		}
		if($identifier == null) {
			//Identifier was 'null', something went wrong, clean up new entry and continue.
			PDOWrapper::deleteByID('mods', $entryID);
			return null;
		}
		
		return new self(
			$entryID,
			$identifier,
			$createdAt,
			$title,
			$caption,
			$user,
		);
	}
	
	public static function getBuilder(): SelectBuilder {
		return QB::select('mods', 'pM')
			->selectColumn('id', 'identifier', 'created_at', 'title', 'caption', 'owner');
		//TBI: Fetch user here? Nah...?
	}
	
	public static function fromDB(array $columns, string $prefix = 'mods.'): self {
		return new self(
			$columns[$prefix. 'id'],
			$columns[$prefix. 'identifier'],
			$columns[$prefix. 'created_at'],
			$columns[$prefix. 'title'],
			$columns[$prefix. 'caption'],
			TableUser::fromDB($columns, fetchUsername: true),
		);
	}
	
	private int $dbID;
	private string $identifier;
	private string $createdAt; //TODO: Datetime
	private string $title;
	private string $caption;
	private Fetchable|TableUser $user;
	
	private function __construct(int $dbID, string $identifier, string $createdAt, string $title, string $caption, Fetchable|TableUser $user) {
		$this->dbID = $dbID;
		$this->identifier = $identifier;
		$this->createdAt = $createdAt;
		$this->title = $title;
		$this->caption = $caption;
		$this->user = $user;
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
	public function getIdentifier(): string {
		return $this->identifier;
	}
	
	/**
	 * @return string
	 */
	public function getCreatedAt(): string {
		return $this->createdAt;
	}
	
	/**
	 * @return string
	 */
	public function getTitle(): string {
		return $this->title;
	}
	
	/**
	 * @return string
	 */
	public function getCaption(): string {
		return $this->caption;
	}
	
	/**
	 * @return TableUser
	 */
	public function getUser(): TableUser {
		if(Fetchable::isFetchable($this->user)) {
			throw new InternalDescriptiveException('Attempted to use mod user, but it was not fetched yet!');
		}
		return $this->user;
	}
	
	public function asFrontEndJSON(): array {
		return [
			'identifier' => $this->identifier,
			'title' => $this->title,
			'caption' => $this->caption,
			'owner' => $this->getUser()->asFrontEndJSON(),
		];
	}
}
