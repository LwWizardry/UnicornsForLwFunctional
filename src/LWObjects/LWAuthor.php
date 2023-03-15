<?php

namespace MP\LWObjects;

class LWAuthor {
	private $id;
	private $username;
	private $picture;
	private $flair;
	
	public function __construct(int $id, string $username, string $picture, string $flair) {
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
	
	public function getPicture(): string {
		return $this->picture;
	}
	
	public function getFlair(): string {
		return $this->flair;
	}
}
