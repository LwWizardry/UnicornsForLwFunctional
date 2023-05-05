<?php

namespace MP\Helpers\QueryBuilder;

class Conditions {
	public static function olderThanHours(string $column, int $hours): array {
		return [$column, '<', 'UTC_TIMESTAMP() - interval ' . $hours . ' hour'];
	}
}