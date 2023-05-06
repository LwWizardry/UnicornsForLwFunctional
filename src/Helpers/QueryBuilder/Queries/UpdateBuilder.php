<?php

namespace MP\Helpers\QueryBuilder\Queries;

use MP\Helpers\QueryBuilder\Internal\ConditionalTrait;
use MP\Helpers\QueryBuilder\Internal\FieldValueTrait;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\PDOWrapper;

class UpdateBuilder extends QueryBuilder {
	use ConditionalTrait;
	use FieldValueTrait;
	
	public function __construct(string $table, string $valuePrefix) {
		parent::__construct($table, $valuePrefix);
	}
	
	protected function build(): string {
		$this->requireFieldValuePairs();
		$this->requireConditions();
		$query = 'UPDATE ' . $this->table . ' SET ';
		$this->generateAssignment($query);
		$this->generateWhereSection($query);
		return $query;
	}
	
	public function execute(): void {
		$statement = PDOWrapper::getPDO()->prepare($this->getQuery());
		$statement->execute($this->arguments);
	}
}
