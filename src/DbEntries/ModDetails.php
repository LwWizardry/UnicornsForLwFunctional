<?php

namespace MP\DbEntries;

use MP\Helpers\QueryBuilder\QueryBuilder as QB;
use MP\Types\LwUserData;
use MP\Types\UserData;

class ModDetails {
	public static function getModFromIdentifier(string $identifier): null|ModDetails {
		$result = QB::select('users')
			->selectColumn('identifier')
			->join(QB::select('mods')
				->selectColumn('title', 'caption')
				->whereValue('identifier', $identifier),
			thatColumn: 'owner')
			->join(QB::select('lw_users')
				->selectColumn('identifier', 'name', 'picture'),
			thatColumn: 'user', optional: true)
			->execute(true);
		if($result === false) {
			return null;
		}
		
		$lwData = $result['lw_user.identifier'] === null || $result['lw_user.name'] === null ?
			null : new LwUserData($result['lw_user.identifier'], $result['lw_user.name'], $result['lw_user.picture']);
		$user = new UserData($result['users.identifier'], $lwData);
		
		return new ModDetails(
			$identifier,
			$result['mods.title'],
			$result['mods.caption'],
			$user,
		);
	}
	
	private string $identifier;
	
	private string $title;
	
	private string $caption;
	
	private UserData $user;
	
	public function __construct(string $identifier, string $title, string $caption, UserData $user) {
		$this->identifier = $identifier;
		$this->title = $title;
		$this->caption = $caption;
		$this->user = $user;
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
	
	public function getUser(): UserData {
		return $this->user;
	}

	public function asFrontEndJSON(): array {
		return [
			'identifier' => $this->identifier,
			'title' => $this->title,
			'caption' => $this->caption,
			'owner' => $this->user->asFrontEndJSON(),
		];
	}
}
