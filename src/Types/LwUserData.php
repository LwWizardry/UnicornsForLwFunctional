<?php

namespace MP\Types;

class LwUserData {
	private int $identifier;
	private string $username;
	private null|string $picture;
	
	public function __construct(int $identifier, string $username, null|string $picture) {
		$this->identifier = $identifier;
		$this->username = $username;
		$this->picture = $picture;
	}
	
	public function getIdentifier(): int {
		return $this->identifier;
	}
	
	public function getPicture(): ?string {
		return $this->picture;
	}
	
	public function getUsername(): string {
		return $this->username;
	}
	
	public function asFrontEndJSON(): array {
		return [
			'id' => $this->identifier,
			'name' => $this->username,
			'picture' => $this->picture,
		];
	}
}
