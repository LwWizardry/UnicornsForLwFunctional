<?php

namespace MP\Helpers;

use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\PDOWrapper;
use PDOException;

class UniqueInjectorHelper {
	public static function largeIdentifier(
		string $table,
		int $id,
		string $column = 'identifier',
		//By default, use 5 bytes, which is 8 letters:
		int $byte_count = 24,
	): string {
		$generator = function() use($byte_count): string {
			$bytes = random_bytes($byte_count);
			return base64_encode($bytes);
		};
		return self::inject($table, $id, $column, $generator);
	}
	
	public static function shortIdentifier(
		string $table,
		int $id,
		string $column = 'identifier',
		//By default, use 5 bytes, which is 8 letters:
		int $byte_count = 5,
	): string {
		$generator = function() use($byte_count): string {
			$bytes = random_bytes($byte_count);
			return Base32::encode($bytes);
		};
		return self::inject($table, $id, $column, $generator);
	}
	
	public static function inject(
		string $table,
		int $id,
		string $column,
		callable $generator,
	): string {
		$query = QueryBuilder::update($table)
			->whereValue('id', $id);
		//Register named placeholder manually, so that its value can be replaced manually for each attempt.
		$valueKey = $query->injectValue(null);
		$query->setValueRaw($column, $valueKey);
		
		$attemptInjection = function() use($query, $valueKey, $generator): null|string {
			try {
				$uniqueValue = $generator();
				$query->overwriteArg($valueKey, $uniqueValue); //Initialize/Update the new unique key to insert
				$query->execute();
				return $uniqueValue;
			} catch (PDOException $e) {
				//Validate, that the expected error happens (unique key constrain issue):
				if(!PDOWrapper::isUniqueConstrainViolation($e)) {
					throw $e;
				}
				return null;
			}
		};
		
		$result = self::repeatedAttempt($attemptInjection);
		if($result === null) {
			throw new InternalDescriptiveException('Failed to generate random identifier for desired entry.');
		}
		return $result;
	}
	
	private static function repeatedAttempt(callable $executable): mixed {
		//There is only a slim chance, that the random value to be injected has a collision.
		// If it nevertheless collides, this will try to find a different value up to 10 times.
		// If this is still an issue in the future, it can be amended.
		for($i = 0; $i < 10; $i++) {
			$result = $executable();
			if($result !== null) {
				return $result;
			}
		}
		//Failed to inject value.
		return null;
	}
}