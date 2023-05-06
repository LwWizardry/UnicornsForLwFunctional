<?php

namespace MP\Helpers\QueryBuilder\Internal;

use Exception;
use MP\Helpers\QueryBuilder\Queries\SelectBuilder;

trait ConditionalTrait {
	private string $mergeType = 'AND';
	private array $conditions = [];
	
	//TBI: This method is like the worst solution - but it is temporary, until I have more use-cases to think about
	// Why design a framework for an unknown use-case.
	public function whereType(string $type): self {
		$this->mergeType = $type;
		return $this;
	}
	
	public function whereValue(string $column, null|string $value, string $cmp = '='): self {
		$key = $this->injectValue($value);
		$this->conditions[] = [$column, $cmp, $key];
		return $this;
	}
	
	//Special conditions:
	
	public function whereOlderThanHours(string $column, int $hours): self {
		$this->conditions[] = [$column, '<', 'UTC_TIMESTAMP() - interval ' . $hours . ' hour'];
		return $this;
	}
	
	public function whereNewerThanHours(string $column, int $hours): self {
		$this->conditions[] = [$column, '>', 'UTC_TIMESTAMP() - interval ' . $hours . ' hour'];
		return $this;
	}
	
	public function whereCondition(array $content): self {
		$this->conditions[] = $content;
		return $this;
	}
	
	//Generators:
	
	protected function requireConditions(): void {
		if(empty($this->conditions)) {
			throw new Exception('Attempted to create a non-select/insert query without any condition!');
		}
	}
	
	protected function generateWhereSection(string &$query, array $conditions = null): void {
		if($conditions === null) {
			$conditions = $this->conditions;
		}
		if(empty($conditions)) {
			return;
		}
		$query .= ' WHERE';
		$query .= join(' ' . $this->mergeType, array_map(
			fn($entry) => ' ' . $entry[0] . ' ' . $entry[1] . ' ' . $entry[2],
			$conditions
		));
	}
	
	private function getLocalConditions(): array {
		return array_map(
			fn($entryInner) => [
				//Always prepend the table to the first entry, which is always a column!
				// If it was not a column, the condition would not work with the table.
				$this->table . '.' . $entryInner[0],
				$entryInner[1],
				$entryInner[2],
			],
			$this->conditions
		);
	}
	
	/**
	 * @param string $query
	 * @param SelectBuilder[] $selectBuilders
	 */
	protected function generateCommonWhereSection(string &$query, array $selectBuilders): void {
		$allConditions = array_merge(...array_map(fn($entry) => $entry->getLocalConditions(), $selectBuilders));
		$this->generateWhereSection($query, $allConditions);
	}
}
