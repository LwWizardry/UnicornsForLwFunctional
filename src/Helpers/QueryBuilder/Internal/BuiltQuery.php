<?php

namespace MP\Helpers\QueryBuilder\Internal;

use Closure;
use MP\PDOWrapper;

class BuiltQuery {
	
	private string $query;
	private array $arguments;
	private null|Closure $executable;
	
	public function __construct(string $query, array $arguments, null|Closure $executable = null) {
		$this->query = $query;
		$this->arguments = $arguments;
		$this->executable = $executable;
	}
	
	public function overwriteArg(string $key, null|string $value): void {
		$this->arguments[':' . $key] = $value;
	}
	
	public function execute(): mixed {
		$statement = PDOWrapper::getPDO()->prepare($this->query);
		$statement->execute($this->arguments);
		return $this->executable?->call($this, $statement);
	}
}
