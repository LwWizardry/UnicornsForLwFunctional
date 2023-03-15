<?php

namespace MP;

class MixedResult {
	private $success;
	private $content;
	
	public function __construct(bool $success, mixed $content) {
		$this->success = $success;
		$this->content = $content;
	}
	
	public function hasFailed(): bool {
		return !$this->success;
	}
	
	public function hasSucceeded(): bool {
		return $this->success;
	}
	
	public function getContent(): mixed {
		return $this->content;
	}
}
