<?php

namespace MP;

use RuntimeException;

class BadRequestException extends RuntimeException {
	public function __construct(string $message) {
		parent::__construct($message);
	}
}
