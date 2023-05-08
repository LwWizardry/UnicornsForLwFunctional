<?php

namespace MP\Helpers\QueryBuilder\Internal;

use Closure;
use MP\PDOWrapper;
use PDOStatement;

class BuiltQuery {
	private PDOStatement $statement;
	private array $arguments;
	private null|Closure $executable;
	
	public function __construct(string $query, array $arguments, null|Closure $executable = null) {
		$this->statement = PDOWrapper::getPDO()->prepare($query);
		$this->arguments = $arguments;
		$this->executable = $executable;
	}
	
	public function overwriteArg(string $key, null|string $value): void {
		$this->arguments[':' . $key] = $value;
	}
	
	public function execute(): mixed {
		$this->statement->execute($this->arguments);
		return $this->executable?->call($this, $this->statement);
	}
}
