<?php

namespace MP\Helpers\QueryBuilder\Internal;

use Exception;

trait ConditionalTrait {
	private array $conditions = [];
	
	public function whereValue(string $column, null|string $value, string $cmp = '='): self {
		$key = $this->injectValue($value);
		$this->conditions[] = [$column, $cmp, $key];
		return $this;
	}
	
	public function whereCondition(array $content): self {
		$this->conditions[] = $content;
		return $this;
	}
	
	protected function requireConditions(): void {
		if(empty($this->whereConditions)) {
			throw new Exception('Attempted to create a non-select/insert query without any condition!');
		}
	}
	
	protected function generateWhereSection(string &$query): void {
		if(empty($this->conditions)) {
			return;
		}
		$query .= ' WHERE';
		$query .= join(' AND', array_map(function ($entry) {
			return ' ' . $entry[0] . ' ' . $entry[1] . ' ' . $entry[2];
		}, $this->conditions));
	}
}
