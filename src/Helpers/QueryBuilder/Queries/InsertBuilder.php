<?php

namespace MP\Helpers\QueryBuilder\Queries;

use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\Internal\BuiltQuery;
use MP\Helpers\QueryBuilder\Internal\FieldValueTrait;
use MP\Helpers\QueryBuilder\QueryBuilder;
use PDOStatement;

class InsertBuilder extends QueryBuilder {
	use FieldValueTrait;
	
	private array $returning = [];
	
	public function __construct(string $table, string $valuePrefix) {
		parent::__construct($table, $valuePrefix);
	}
	
	public function return(string ...$columns): self {
		foreach($columns as $column) {
			$this->returning[] = $column;
		}
		return $this;
	}
	
	public function build(): BuiltQuery {
		$this->requireFieldValuePairs();
		
		$query = 'INSERT INTO ' . $this->table . ' (';
		$this->generateFieldList($query);
		$query .= ') VALUES (';
		$this->generateValueList($query);
		$query .= ')';
		$isReturning = !empty($this->returning);
		if($isReturning) {
			$query .= ' RETURNING';
			$query .= join(',', array_map(function ($entry) {
				return ' ' . $entry;
			}, $this->returning));
		}
		
		$isReturningOneColumn = count($this->returning) === 1;
		return new BuiltQuery($query, $this->arguments, function($statement) use ($isReturning, $isReturningOneColumn) {
			//Return values (if needed):
			if(!$isReturning) {
				//No return value expected, just default to null.
				return null;
			}
			
			if($isReturningOneColumn) {
				$result = $statement->fetchColumn();
			} else {
				$result = $statement->fetch();
			}
			if($result === false) {
				throw new InternalDescriptiveException('Fetch failed (false), while trying to insert a new thing.');
			}
			return $result;
		});
	}
	
	public function execute(): mixed {
		return $this->build()->execute();
	}
}
