<?php

namespace MP\Helpers\QueryBuilder\Queries;

use MP\Helpers\QueryBuilder\Internal\BuiltQuery;
use MP\Helpers\QueryBuilder\Internal\ConditionalTrait;
use MP\Helpers\QueryBuilder\Internal\FieldValueTrait;
use MP\Helpers\QueryBuilder\QueryBuilder;

class UpdateBuilder extends QueryBuilder {
	use ConditionalTrait;
	use FieldValueTrait;
	
	public function __construct(string $table, string $valuePrefix) {
		parent::__construct($table, $valuePrefix);
	}
	
	public function build(): BuiltQuery {
		$this->requireFieldValuePairs();
		$this->requireConditions();
		
		$query = 'UPDATE ' . $this->table . ' SET ';
		$this->generateAssignment($query);
		$this->generateWhereSection($query);
		
		return new BuiltQuery($query, $this->arguments);
	}
	
	public function execute(): void {
		$this->build()->execute();
	}
}
