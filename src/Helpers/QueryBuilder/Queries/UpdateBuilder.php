<?php

namespace MP\Helpers\QueryBuilder\Queries;

use MP\Helpers\QueryBuilder\Internal\ConditionalTrait;
use MP\Helpers\QueryBuilder\Internal\FieldValueTrait;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\PDOWrapper;

class UpdateBuilder extends QueryBuilder {
	use ConditionalTrait;
	use FieldValueTrait;
	
	public function __construct(string $table) {
		parent::__construct($table);
	}
	
	public function execute(): void {
		$query = 'UPDATE ' . $this->table . ' SET ';
		//Values:
		$this->requireFieldValuePairs();
		$this->generateAssignment($query);
		//Condition:
		$this->requireConditions();
		$this->generateWhereSection($query);
		
		$statement = PDOWrapper::getPDO()->prepare($query);
		$statement->execute($this->arguments);
	}
}
