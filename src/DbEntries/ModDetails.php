<?php

namespace MP\DbEntries;

use MP\PDOWrapper;
use MP\Types\LwUserData;
use MP\Types\UserData;

class ModDetails {
	public static function getModFromIdentifier(string $identifier): null|ModDetails {
		$statement = PDOWrapper::getPDO()->prepare('
				SELECT m.title, m.caption,
					u.identifier as u_identifier,
					
					lu.identifier as lw_id,
					lu.name as lw_name,
					lu.picture as lw_picture
				FROM mods m
				INNER JOIN users u on m.owner = u.id
				LEFT JOIN lw_users lu on u.id = lu.user
				WHERE m.identifier = :identifier
			');
		$statement->execute([
			'identifier' => $identifier,
		]);
		$result = $statement->fetch();
		if($result === false) {
			return null;
		}
		
		$lwData = $result['lw_id'] === null || $result['lw_name'] === null ?
			null : new LwUserData($result['lw_id'], $result['lw_name'], $result['lw_picture']);
		$user = new UserData($result['u_identifier'], $lwData);
		
		return new ModDetails(
			$identifier,
			$result['title'],
			$result['caption'],
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
