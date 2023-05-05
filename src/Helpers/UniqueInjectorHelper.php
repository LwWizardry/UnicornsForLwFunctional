<?php

namespace MP\Helpers;

use MP\ErrorHandling\InternalDescriptiveException;
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
		//TBI: is it worth modifying the query builder to be able to be used here?
		$statement = PDOWrapper::getPDO()->prepare('
			UPDATE ' . $table .'
			SET ' . $column . ' = :value
			WHERE id = :id
		');
		$arguments = [
			'id' => $id,
		];
		
		$attemptInjection = function() use($statement, $column, &$arguments, $generator): null|string {
			try {
				$uniqueValue = $generator();
				$arguments['value'] = $uniqueValue; //Initialize/Update the new unique key to insert
				$statement->execute($arguments);
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