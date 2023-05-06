<?php

namespace MP\Helpers\QueryBuilder\Queries;

use MP\Helpers\QueryBuilder\Internal\BuiltQuery;
use MP\Helpers\QueryBuilder\Internal\ConditionalTrait;
use MP\Helpers\QueryBuilder\QueryBuilder;

class DeleteBuilder extends QueryBuilder {
	use ConditionalTrait;
	
	public function __construct(string $table, string $valuePrefix) {
		parent::__construct($table, $valuePrefix);
	}
	
	public function build(): BuiltQuery {
		$this->requireConditions();
		
		$query = 'DELETE FROM ' . $this->table;
		$this->generateWhereSection($query);
		
		return new BuiltQuery($query, $this->arguments);
	}
	
	public function execute(): void {
		$this->build()->execute();
	}
}
