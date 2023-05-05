<?php

namespace MP\Helpers\QueryBuilder;

use MP\Helpers\QueryBuilder\Queries\DeleteBuilder;
use MP\Helpers\QueryBuilder\Queries\InsertBuilder;
use MP\Helpers\QueryBuilder\Queries\SelectBuilder;
use MP\Helpers\QueryBuilder\Queries\UpdateBuilder;

abstract class QueryBuilder {
	protected string $table;
	
	private string $valuePrefix;
	private int $valueIndex = 0;
	protected array $arguments = [];
	
	public function __construct(string $table, string $valuePrefix) {
		$this->table = $table;
		$this->valuePrefix = $valuePrefix;
	}
	
	public function injectValue(null|string $value): string {
		$key = ':' . $this->valuePrefix . $this->valueIndex++;
		$this->arguments[$key] = $value;
		return $key;
	}
	
	//Static constructors:
	
	public static function select(string $table, string $valuePrefix = 'v'): SelectBuilder {
		return new SelectBuilder($table, $valuePrefix);
	}
	
	public static function insert(string $table, string $valuePrefix = 'v'): InsertBuilder {
		return new InsertBuilder($table, $valuePrefix);
	}
	
	public static function update(string $table, string $valuePrefix = 'v'): UpdateBuilder {
		return new UpdateBuilder($table, $valuePrefix);
	}
	
	public static function delete(string $table, string $valuePrefix = 'v'): DeleteBuilder {
		return new DeleteBuilder($table, $valuePrefix);
	}
}
