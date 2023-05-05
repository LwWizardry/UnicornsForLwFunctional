<?php

namespace MP\Helpers\QueryBuilder;

use Exception;
use MP\PDOWrapper;

class DeleteBuilder {
	private string $table;
	
	private int $valueIndex = 0;
	private array $arguments = [];
	
	private array $whereConditions = [];
	
	public function __construct(string $table) {
		$this->table = $table;
	}
	
	public function whereValue(string $column, null|string $value, string $cmp = '='): DeleteBuilder {
		$key = $this->injectValue($value);
		$this->whereConditions[] = [$column, $cmp, $key];
		return $this;
	}
	
	public function whereCondition(array $content): DeleteBuilder {
		$this->whereConditions[] = $content;
		return $this;
	}
	
	public function injectValue(null|string $value): string {
		$key = ':v' . $this->valueIndex++;
		$this->arguments[$key] = $value;
		return $key;
	}
	
	public function execute(): void {
		$query = 'DELETE FROM ' . $this->table;
		//Condition:
		if(empty($this->whereConditions)) {
			throw new Exception('Attempted to create a DELETE query without any condition!');
		}
		$query .= ' WHERE';
		$query .= join(' AND', array_map(function ($entry) {
			return ' ' . $entry[0] . ' ' . $entry[1] . ' ' . $entry[2];
		}, $this->whereConditions));
		
		$statement = PDOWrapper::getPDO()->prepare($query);
		$statement->execute($this->arguments);
	}
}