<?php

namespace MP;

use DateTime;
use DateTimeZone;
use PDO;
use PDOException;
use PDOStatement;

class PDOWrapper {
	private static null|PDO $pdo = null;
	
	public static function getPDO(): PDO {
		if (self::$pdo === null) {
			self::$pdo = new PDO(
				'mysql:host=localhost;dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
				$_ENV['DB_USER'],
				$_ENV['DB_PASSWORD'],
				[
					PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
					PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
					PDO::ATTR_EMULATE_PREPARES => false,
				]
			);
		}
		return self::$pdo;
	}
	
	//Unused, as replaced with 'UTC_TIMESTAMP'
	public static function getDateNow(): string {
		$date = new DateTime("now", new DateTimeZone("UTC"));
		return $date->format('Y-m-d H:i:s'); //Required: YYYY-MM-DD HH:MI:SS
	}
	
	public static function isUniqueConstrainViolation(PDOException $e): bool {
		return $e->getCode() == 23000 && str_starts_with($e->getMessage(), 'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'');
	}
	
	//TBI: Some say this method of finding unique IDs is bad, due to unpredictable runtime. I say it is pure, as the result is truly random.
	public static function uniqueInjector(PDOStatement $query, array $arguments, string $uniqueKey, callable $generationCallback, bool $returningQuery = false) : null|string|array {
		$attemptInjection = function() use($query, &$arguments, $uniqueKey, $generationCallback, $returningQuery): null|string|array {
			try {
				$uniqueValue = $generationCallback();
				$arguments[$uniqueKey] = $uniqueValue; //Initialize/Update the new unique key to insert
				$query->execute($arguments);
				if(!$returningQuery) {
					return $uniqueValue;
				}
				$result = $query->fetch(); //Return the unique key that is actually being used - to reference it later.
				if($result === false) {
					throw new InternalDescriptiveException('Fetch failed (false), while trying to unique inject an entry.');
				}
				return $result;
			} catch (PDOException $e) {
				//Validate, that the expected error happens (unique key constrain issue):
				if(!self::isUniqueConstrainViolation($e)) {
					throw $e;
				}
				return null;
			}
		};
		
		return self::repeatedAttempt($attemptInjection);
	}
	
	private static function repeatedAttempt(callable $executable): mixed {
		$startTime = microtime(true);
		
		//Try at least 5 times:
		for($i = 0; $i < 5; $i++) {
			$result = $executable();
			if($result !== null) {
				return $result;
			}
		}
		
		//If the duration exceeded half a second, stop - we do not want to flood the DB - or wait forever - something is wrong here!
		while((microtime(true) - $startTime) < 0.5) {
			$result = $executable();
			if($result !== null) {
				return $result;
			}
		}
		
		//Tried for longer than half a second, stop trying now.
		return null;
	}
	
	public static function uniqueIdentifierInjector(string $table, string $column, int $id, callable $idGenerator) : null|string {
		$statement = PDOWrapper::getPDO()->prepare('
			UPDATE ' . $table .'
			SET ' . $column . ' = :value
			WHERE id = :id
		');
		$arguments = [
			'id' => $id,
		];
		
		$attemptInjection = function() use($statement, $column, &$arguments, $idGenerator): null|string {
			try {
				$uniqueValue = $idGenerator();
				$arguments['value'] = $uniqueValue; //Initialize/Update the new unique key to insert
				$statement->execute($arguments);
				return $uniqueValue;
			} catch (PDOException $e) {
				//Validate, that the expected error happens (unique key constrain issue):
				if(!self::isUniqueConstrainViolation($e)) {
					throw $e;
				}
				return null;
			}
		};
		
		return self::repeatedAttempt($attemptInjection);
	}
}
