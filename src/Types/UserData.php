<?php

namespace MP\Types;

class UserData {
	private string $identifier;
	private null|LwUserData $lwData;
	
	public function __construct(string $identifier, null|LwUserData $lwData) {
		$this->identifier = $identifier;
		$this->lwData = $lwData;
	}
	
	public function getIdentifier(): string {
		return $this->identifier;
	}
	
	public function getLwData(): ?LwUserData {
		return $this->lwData;
	}
	
	public function asFrontEndJSON(): array {
		return [
			'identifier' => $this->identifier,
			'lw_data' => $this->lwData?->asFrontEndJSON(),
		];
	}
}
