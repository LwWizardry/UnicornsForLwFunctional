<?php

namespace MP\DatabaseTables\Generic;

class Fetchable {
	public static null|Fetchable $instance = null;
	
	public static function i(): Fetchable {
		if(self::$instance === null) {
			self::$instance = new Fetchable();
		}
		return self::$instance;
	}
	
	public static function isFetchable(mixed $value): bool {
		return $value === Fetchable::i();
	}
}
