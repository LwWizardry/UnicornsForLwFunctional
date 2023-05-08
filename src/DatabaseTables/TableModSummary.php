<?php

namespace MP\DatabaseTables;

use Exception;
use MP\DatabaseTables\Generic\Fetchable;
use MP\Helpers\QueryBuilder\Queries\SelectBuilder;
use MP\Helpers\QueryBuilder\QueryBuilder as QB;

class TableModSummary {
	public static function getModFromIdentifier(string $identifier, bool $fetchUsername = false): null|self {
		$result = self::getBuilder(fetchUser: $fetchUsername)
			->whereValue('identifier', $identifier)
			->expectOneRow()
			->execute();
		if($result === false) {
			return null;
		}
		return self::fromDB($result);
	}
	
	/**
	 * @return TableModSummary[]
	 */
	public static function getSummariesForUser(TableUser $user): array {
		//As this is for a user, there is no need to fetch the username.
		$result = self::getBuilder()
			->whereValue('owner', $user->getDbId())
			->execute();
		
		$mods = [];
		foreach($result as $entry) {
			$mods[] = self::fromDB($entry, prefix: '');
		}
		return $mods;
	}
	
	public static function getBuilder(bool $fetchUser = false): SelectBuilder {
		$query = QB::select('mods', 'pMS')
			->selectColumn('identifier', 'title', 'caption');
		if($fetchUser) {
			$query->joinThis('owner', TableUser::getBuilder(fetchUsername: true));
		}
		return $query;
	}
	
	public static function fromDB(
		array $columns, string $prefix = 'mods.',
		bool $fetchUsername = false,
	): self {
		return new self(
			$columns[$prefix. 'identifier'],
			$columns[$prefix. 'title'],
			$columns[$prefix. 'caption'],
			$fetchUsername ? TableUser::fromDB($columns, fetchUsername: true) : Fetchable::i(),
		);
	}
	
	private string $identifier;
	
	private string $title;
	
	private string $caption;
	
	private Fetchable|TableUser $owner;
	
	//Data structure is meant as a shortcut for explicitly getting data, hence no ID is needed to update data.
	private function __construct(string $identifier, string $title, string $caption, Fetchable|TableUser $owner) {
		$this->identifier = $identifier;
		$this->title = $title;
		$this->caption = $caption;
		$this->owner = $owner;
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
	 * @throws Exception When owner is not fetched yet. (Access this table by different means, or fetch on load).
	 */
	public function getOwner(): TableUser {
		if(Fetchable::isFetchable($this->owner)) {
			throw new Exception('Tried to get get fetchable mod summary owner, but it was not set yet.');
		}
		return $this->owner;
	}
	
	public function asFrontEndJSON(): array {
		return [
			'identifier' => $this->identifier,
			'title' => $this->title,
			'caption' => $this->caption,
		];
	}
}
