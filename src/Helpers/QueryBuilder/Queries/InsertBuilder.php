<?php

namespace MP\Helpers\QueryBuilder\Queries;

use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\Internal\FieldValueTrait;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\PDOWrapper;

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
	
	protected function build(): string {
		$this->requireFieldValuePairs();
		$query = 'INSERT INTO ' . $this->table . ' (';
		$this->generateFieldList($query);
		$query .= ') VALUES (';
		$this->generateValueList($query);
		$query .= ')';
		if(!empty($this->returning)) {
			$query .= ' RETURNING';
			$query .= join(',', array_map(function ($entry) {
				return ' ' . $entry;
			}, $this->returning));
		}
		
		return $query;
	}
	
	public function execute(): mixed {
		$statement = PDOWrapper::getPDO()->prepare($this->getQuery());
		$statement->execute($this->arguments);
		
		//Return values (if needed):
		if(empty($this->returning)) {
			//No return value expected, just default to null.
			return null;
		}
		
		if(count($this->returning) === 1) {
			$result = $statement->fetchColumn();
		} else {
			$result = $statement->fetch();
		}
		if($result === false) {
			throw new InternalDescriptiveException('Fetch failed (false), while trying to insert a new thing.');
		}
		return $result;
	}
}
