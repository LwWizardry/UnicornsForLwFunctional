<?php

namespace MP\Helpers\QueryBuilder;

use Exception;
use MP\PDOWrapper;

class UpdateBuilder {
	private string $table;
	
	private int $valueIndex = 0;
	private array $updateValues = [];
	private array $arguments = [];
	
	private array $whereConditions = [];
	
	public function __construct(string $table) {
		$this->table = $table;
	}
	
	public function setValue(string $column, null|string $value): UpdateBuilder {
		$key = $this->injectValue($value);
		$this->updateValues[] = [$column, $key];
		return $this;
	}
	
	public function setValues(array $values): UpdateBuilder {
		foreach($values as $entry) {
			$this->setValue($entry[0], $entry[1]);
		}
		return $this;
	}
	
	public function setUTC(string $column): UpdateBuilder {
		$this->updateValues[] = [$column, 'UTC_TIMESTAMP()'];
		return $this;
	}
	
	public function whereValue(string $column, null|string $value, string $cmp = '='): UpdateBuilder {
		$key = $this->injectValue($value);
		$this->whereConditions[] = [$column, $cmp, $key];
		return $this;
	}
	
	public function whereCondition(array $content): UpdateBuilder {
		$this->whereConditions[] = $content;
		return $this;
	}
	
	public function injectValue(null|string $value): string {
		$key = ':v' . $this->valueIndex++;
		$this->arguments[$key] = $value;
		return $key;
	}
	
	public function execute(): void {
		$query = 'UPDATE ' . $this->table . ' SET';
		//Values:
		if(empty($this->updateValues)) {
			throw new Exception('Attempted to create an UPDATE query without updating any fields!');
		}
		$query .= join(',', array_map(function ($entry) {
			return ' ' . $entry[0] . ' = ' . $entry[1];
		}, $this->updateValues));
		//Condition:
		if(empty($this->whereConditions)) {
			throw new Exception('Attempted to create an UPDATE query without any condition!');
		}
		$query .= ' WHERE';
		$query .= join(' AND', array_map(function ($entry) {
			return ' ' . $entry[0] . ' ' . $entry[1] . ' ' . $entry[2];
		}, $this->whereConditions));
		
		$statement = PDOWrapper::getPDO()->prepare($query);
		$statement->execute($this->arguments);
	}
}