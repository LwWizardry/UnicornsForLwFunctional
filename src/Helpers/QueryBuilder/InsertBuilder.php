<?php

namespace MP\Helpers\QueryBuilder;

use Exception;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\PDOWrapper;

class InsertBuilder {
	private string $table;
	
	private int $valueIndex = 0;
	private array $arguments = [];
	
	private array $insertValues = [];
	
	private array $returning = [];
	
	public function __construct(string $table) {
		$this->table = $table;
	}
	
	public function setValues(array $values): InsertBuilder {
		foreach($values as $key => $value) {
			$this->setValue($key, $value);
		}
		return $this;
	}
	
	public function setValue(string $column, null|string $value): InsertBuilder {
		$key = $this->injectValue($value);
		$this->insertValues[] = [$column, $key];
		return $this;
	}
	
	public function setUTC(string $column): InsertBuilder {
		$this->insertValues[] = [$column, 'UTC_TIMESTAMP()'];
		return $this;
	}
	
	public function injectValue(null|string $value): string {
		$key = ':v' . $this->valueIndex++;
		$this->arguments[$key] = $value;
		return $key;
	}
	
	public function return(string $column): InsertBuilder {
		$this->returning[] = $column;
		return $this;
	}
	
	public function returns(array $columns): InsertBuilder {
		foreach($columns as $column) {
			self::return($column);
		}
		return $this;
	}
	
	public function execute(): mixed {
		$query = 'INSERT INTO ' . $this->table . ' (';
		
		//Fields:
		if(empty($this->insertValues)) {
			throw new Exception('Attempted to create an UPDATE query without updating any fields!');
		}
		$query .= join(',', array_map(function ($entry) {
			return ' ' . $entry[0];
		}, $this->insertValues));
		$query .= ') VALUES (';
		//Values:
		$query .= join(',', array_map(function ($entry) {
			return ' ' . $entry[1];
		}, $this->insertValues));
		$query .= ')';
		
		//Condition:
		$isReturning = !empty($this->returning);
		if($isReturning) {
			$query .= ' RETURNING';
			$query .= join(',', array_map(function ($entry) {
				return ' ' . $entry;
			}, $this->returning));
		}
		
		$statement = PDOWrapper::getPDO()->prepare($query);
		$statement->execute($this->arguments);
		//Return values (if needed):
		if($isReturning) {
			if(count($this->returning) === 1) {
				$result = $statement->fetchColumn();
				
			} else {
				$result = $statement->fetch();
			}
			if($result === false) {
				throw new InternalDescriptiveException('Fetch failed (false), while trying to insert a new thing.');
			}
			return $result;
		} else {
			//No return value expected, just default to null.
			return null;
		}
	}
}