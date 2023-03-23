<?php

namespace MP\LWObjects;

class LWAuthor {
	private int $id;
	private string $username;
	private null|string $picture;
	private null|string $flair;
	
	public function __construct(int $id, string $username, null|string $picture, null|string $flair) {
		$this->id = $id;
		$this->username = $username;
		$this->picture = $picture;
		$this->flair = $flair;
	}
	
	public function getId(): int {
		return $this->id;
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
