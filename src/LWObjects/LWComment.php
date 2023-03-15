<?php

namespace MP\LWObjects;

class LWComment {
	private $id;
	private $body;
	private $author;
	
	public function __construct(string $id, string $body, LWAuthor $author) {
		$this->id = $id;
		$this->body = $body;
		$this->author = $author;
	}
	
	public function getId(): string {
		return $this->id;
	}
	
	public function getBody(): string {
		return $this->body;
	}
	
	public function getAuthor(): LWAuthor {
		return $this->author;
	}
}
