<?php

namespace MP\LWObjects;

use DateTime;

class LWComment {
	private string $id;
	private string $body;
	private LWAuthor $author;
	private DateTime $createdAt;
	private null|DateTime $editedAt;
	
	public function __construct(string $id, string $body, LWAuthor $author, DateTime $createdAt, null|DateTime $editedAt) {
		$this->id = $id;
		$this->body = $body;
		$this->author = $author;
		$this->createdAt = $createdAt;
		$this->editedAt = $editedAt;
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
	
	public function getCreatedAt(): DateTime {
		return $this->createdAt;
	}
	
	public function getEditedAt(): null|DateTime {
		return $this->editedAt;
	}
	
	public function isEdited(): bool {
		return $this->editedAt !== null;
	}
}
