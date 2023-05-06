<?php

namespace MP\Helpers\QueryBuilder\Internal;

use Exception;

trait FieldValueTrait {
	private array $fieldValues = [];
	
	public function setValueRaw(string $column, null|string $value): self {
		$this->fieldValues[] = [$column, $value];
		return $this;
	}
	
	public function setValue(string $column, null|string $value): self {
		$key = $this->injectValue($value);
		return $this->setValueRaw($column, $key);
	}
	
	public function setValues(array $values): self {
		foreach($values as $key => $value) {
			$this->setValue($key, $value);
		}
		return $this;
	}
	
	public function setUTC(string $column): self {
		return $this->setValueRaw($column, 'UTC_TIMESTAMP()');
	}
	
	protected function requireFieldValuePairs(): void {
		if(empty($this->fieldValues)) {
			throw new Exception('Attempted to create an INSERT/UPDATE query without setting any fields!');
		}
	}
	
	protected function generateAssignment(string &$query): void {
		$query .= join(', ', array_map(function ($entry) {
			return $entry[0] . ' = ' . $entry[1];
		}, $this->fieldValues));
	}
	
	protected function generateFieldList(string &$query): void {
		$query .= join(', ', array_map(function ($entry) {
			return $entry[0];
		}, $this->fieldValues));
	}
	
	protected function generateValueList(string &$query): void {
		$query .= join(',', array_map(function ($entry) {
			return ' ' . $entry[1];
		}, $this->fieldValues));
	}
}
