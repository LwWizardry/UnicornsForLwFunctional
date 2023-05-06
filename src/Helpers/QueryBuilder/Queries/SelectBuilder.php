<?php

namespace MP\Helpers\QueryBuilder\Queries;

use Exception;
use MP\Helpers\QueryBuilder\Internal\BuiltQuery;
use MP\Helpers\QueryBuilder\Internal\ConditionalTrait;
use MP\Helpers\QueryBuilder\QueryBuilder;
use PDOStatement;

class SelectBuilder extends QueryBuilder {
	use ConditionalTrait;
	
	private bool $expectOneRow = false;
	
	public function __construct(string $table, string $valuePrefix) {
		parent::__construct($table, $valuePrefix);
	}
	
	public function expectOneRow(): self {
		$this->expectOneRow = true;
		return $this;
	}
	
	//### SELECT ###
	
	private array $selectors = [];
	
	public function selectColumn(string ...$columns): self {
		foreach($columns as $column) {
			$this->selectors[] = $column;
		}
		return $this;
	}
	
	private function getSelectors(bool $prefixed = false): array {
		if($prefixed) {
			return array_map(
				fn($column) => $this->table . '.' . $column . ' AS \'' . $this->table . '.' . $column . '\'',
				$this->selectors
			);
		} else {
			return $this->selectors;
		}
	}
	
	//### JOIN ###
	
	/**
	 * @var SelectBuilder[]
	 */
	private array $tableBuilders = [];
	private array $joins = [];
	
	public function joinThis(string $column, SelectBuilder $tableBuilder, string $type = 'INNER'): self {
		return $this->join($tableBuilder, thisColumn: $column, type: $type);
	}
	
	public function joinThat(string $column, SelectBuilder $tableBuilder, string $type = 'INNER'): self {
		return $this->join($tableBuilder, thatColumn: $column, type: $type);
	}
	
	public function join(SelectBuilder $tableBuilder, string $thisColumn = 'id', string $thatColumn = 'id', string $type = 'INNER'): self {
		$this->tableBuilders[] = $tableBuilder;
		$this->joins[] = [
			//Type of join: INNER/LEFT(/RIGHT):
			$type,
			//Table to join:
			$tableBuilder->table,
			//This Column:
			$this->table . '.' . $thisColumn,
			//That Column:
			$tableBuilder->table . '.' . $thatColumn,
		];
		return $this;
	}
	
	private function getJoins(): array {
		return array_merge($this->joins, ...array_map(fn($tableBuilder) => $tableBuilder->getJoins(), $this->tableBuilders));
	}
	
	/**
	 * @return SelectBuilder[]
	 */
	private function getBuilders(): array {
		return array_merge([$this], ...array_map(fn($tableBuilder) => $tableBuilder->getBuilders(), $this->tableBuilders));
	}
	
	public function build(): BuiltQuery {
		$allTableBuilders = $this->getBuilders();
		$isMultipleTables = count($allTableBuilders) > 1;
		
		//Collect all selectors:
		$allSelectors = array_merge(...array_map(fn($entry) => $entry->getSelectors($isMultipleTables), $allTableBuilders));
		if(empty($allSelectors)) {
			if($isMultipleTables) {
				throw new Exception('Nothing to select in the select query.');
			} else {
				$allSelectors = ['*'];
			}
		}
		
		//Building:
		$query = 'SELECT ';
		$query .= join(', ', $allSelectors);
		$query .= ' FROM ' . $this->table;
		
		//Joins:
		$allJoins = $this->getJoins();
		if(!empty($allJoins)) {
			foreach($allJoins as $join) {
				$query .= ' ' . $join[0] . ' JOIN ' . $join[1] . ' ON ' . $join[2] . ' = ' . $join[3];
			}
		}
		
		//Conditional building:
		if($isMultipleTables) {
			$this->generateCommonWhereSection($query, $allTableBuilders);
		} else {
			$this->generateWhereSection($query);
		}
		
		$allArguments = array_merge(...array_map(fn($e) => $e->arguments, $allTableBuilders));
		$expectOneRow = $this->expectOneRow;
		$onlyOneColumn = count($allSelectors) === 1 && $allSelectors[0] !== '*';
		return new BuiltQuery($query, $allArguments, function($statement) use($expectOneRow, $onlyOneColumn) {
			if($expectOneRow) {
				if($onlyOneColumn) {
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
		});
	}
	
	public function execute(): mixed {
		return $this->build()->execute();
	}
}
