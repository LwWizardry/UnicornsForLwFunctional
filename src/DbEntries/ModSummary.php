<?php

namespace MP\DbEntries;

use MP\Helpers\UniqueInjectorHelper;
use MP\PDOWrapper;
use PDOException;

class ModSummary {
	public static function getModFromIdentifier(string $identifier): null|ModSummary {
		$statement = PDOWrapper::getPDO()->prepare('
			SELECT title, caption
			FROM mods
			WHERE identifier = :identifier
		');
		$statement->execute([
			'identifier' => $identifier,
		]);
		$result = $statement->fetch();
		if($result === false) {
			return null;
		}
		
		return new ModSummary(
			$identifier,
			$result['title'],
			$result['caption'],
		);
	}
	
	public static function addNewMod(string $title, string $caption, User $user): null|ModSummary {
		//TODO: Improve title validation, remove "'" and other funny characters.
		// Can probably be expanded on demand.
		//Sanitise title, by making it lowercase:
		$title_sane = mb_strtolower($title);
		
		//Inject a mod entry into DB, Title/title_normalized/Caption - Warning: Title_sane can be a duplicate!
		
		try {
			$entryID = PDOWrapper::insertAndFetchColumn('
				INSERT INTO mods (title, title_sane, caption, created_at, owner)
				VALUES (:title, :title_sane, :caption, UTC_TIMESTAMP(), :owner)
				RETURNING id
			', [
				'title' => $title,
				'title_sane' => $title_sane,
				'caption' => $caption,
				'owner' => $user->getDbId(),
			]);
		} catch (PDOException $e) {
			if(PDOWrapper::isUniqueConstrainViolation($e)) {
				return null;
			}
			throw $e;
		}
		
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
		
		return new ModSummary(
			$identifier,
			$title,
			$caption,
		);
	}
	
	/**
	 * @return ModSummary[]
	 */
	public static function getSummariesForUser(User $user): array {
		$statement = PDOWrapper::getPDO()->prepare('
			SELECT identifier, title, caption
			FROM mods
			WHERE owner = :owner
		');
		$statement->execute([
			'owner' => $user->getDbId(),
		]);
		$result = $statement->fetchAll();
		
		$mods = [];
		foreach($result as $entry) {
			$mods[] = new ModSummary(
				$entry['identifier'],
				$entry['title'],
				$entry['caption'],
			);
		}
		return $mods;
	}
	
	private string $identifier;
	
	private string $title;
	
	private string $caption;
	
	public function __construct(string $identifier, string $title, string $caption) {
		$this->identifier = $identifier;
		$this->title = $title;
		$this->caption = $caption;
	}
	
	public function getIdentifier(): string {
		return $this->identifier;
	}
	
	public function getTitle(): string {
		return $this->title;
	}
	
	public function getCaption(): string {
		return $this->caption;
	}
	
	public function asFrontEndJSON(): array {
		return [
			'identifier' => $this->identifier,
			'title' => $this->title,
			'caption' => $this->caption,
		];
	}
}
