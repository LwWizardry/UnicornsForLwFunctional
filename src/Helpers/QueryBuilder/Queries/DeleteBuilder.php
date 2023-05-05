<?php

namespace MP\Helpers\QueryBuilder\Queries;

use MP\Helpers\QueryBuilder\Internal\ConditionalTrait;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\PDOWrapper;

class DeleteBuilder extends QueryBuilder {
	use ConditionalTrait;
	
	public function __construct(string $table) {
		parent::__construct($table);
	}
	
	public function execute(): void {
		$query = 'DELETE FROM ' . $this->table;
		//Condition:
		$this->requireConditions();
		$this->generateWhereSection($query);
		
		$statement = PDOWrapper::getPDO()->prepare($query);
		$statement->execute($this->arguments);
	}
}
