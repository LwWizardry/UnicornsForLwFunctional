<?php

namespace MP\Helpers\QueryBuilder;

use MP\Helpers\QueryBuilder\Queries\DeleteBuilder;
use MP\Helpers\QueryBuilder\Queries\InsertBuilder;
use MP\Helpers\QueryBuilder\Queries\SelectBuilder;
use MP\Helpers\QueryBuilder\Queries\UpdateBuilder;

abstract class QueryBuilder {
	protected string $table;
	
	private int $valueIndex = 0;
	protected array $arguments = [];
	
	public function __construct(string $table) {
		$this->table = $table;
	}
	
	public function injectValue(null|string $value): string {
		$key = ':v' . $this->valueIndex++;
		$this->arguments[$key] = $value;
		return $key;
	}
	
	//Static constructors:
	
	public static function select(string $table): SelectBuilder {
		return new SelectBuilder($table);
	}
	
	public static function insert(string $table): InsertBuilder {
		return new InsertBuilder($table);
	}
	
	public static function update(string $table): UpdateBuilder {
		return new UpdateBuilder($table);
	}
	
	public static function delete(string $table): DeleteBuilder {
		return new DeleteBuilder($table);
	}
}
