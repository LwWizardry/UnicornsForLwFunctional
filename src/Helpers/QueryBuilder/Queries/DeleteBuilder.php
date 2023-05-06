<?php

namespace MP\Helpers\QueryBuilder\Queries;

use MP\Helpers\QueryBuilder\Internal\ConditionalTrait;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\PDOWrapper;

class DeleteBuilder extends QueryBuilder {
	use ConditionalTrait;
	
	public function __construct(string $table, string $valuePrefix) {
		parent::__construct($table, $valuePrefix);
	}
	
	protected function build(): string {
		$this->requireConditions();
		$query = 'DELETE FROM ' . $this->table;
		$this->generateWhereSection($query);
		return $query;
	}
	
	public function execute(): void {
		$statement = PDOWrapper::getPDO()->prepare($this->getQuery());
		$statement->execute($this->arguments);
	}
}
