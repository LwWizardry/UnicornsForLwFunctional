<?php

namespace MP\Helpers\QueryBuilder\Queries;

use MP\Helpers\QueryBuilder\Internal\ConditionalTrait;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\PDOWrapper;

class SelectBuilder extends QueryBuilder {
	use ConditionalTrait;
	
	public function __construct(string $table) {
		parent::__construct($table);
	}
	
	//### SELECT ###
	
	private array $selectors = [];
	
	public function selectColumn(string $column): self {
		
		return $this;
	}
	
	public function selectColumns(string ...$columns): self {
		
		return $this;
	}
	
	//### JOIN ###
	
	/*
	public function join(string $foreignColumn, SelectBuilder $tableBuilder, bool $optional = false) {
	
	}
	*/
	
	public function execute(bool $expectOneRow = false): mixed {
		//TODO: Collect selectors!
		$allSelectors = $this->selectors;
		
		//Validation:
		if(empty($allSelectors)) {
			//throw new Exception('Nothing to select in the select query.');
			//TODO: Do not all '*', when joins are involved.
			$allSelectors = ['*'];
		}
		
		//Building:
		$query = 'SELECT ';
		$query .= join(', ', array_map(function ($entry) {
			return $entry;
		}, $allSelectors));
		$query .= ' FROM ' . $this->table;
		//Conditional building:
		$this->generateWhereSection($query);
		
		$statement = PDOWrapper::getPDO()->prepare($query);
		$statement->execute($this->arguments);
		
		if($expectOneRow) {
			if(count($allSelectors) === 1 && $allSelectors[0] !== '*') {
				//FALSE or MIXED (careful when returning boolean!):
				return $statement->fetchColumn();
			} else {
				//FALSE or MIXED (careful when returning boolean!):
				return $statement->fetch();
			}
		} else {
			//FALSE or ARRAY of rows:
			return $statement->fetchAll();
		}
	}
}
