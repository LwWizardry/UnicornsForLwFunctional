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
	
	//Unused, as replaced with 'UTC_TIME'
	public static function getDateNow(): string {
		$date = new DateTime("now", new DateTimeZone("UTC"));
		return $date->format('Y-m-d H:i:s'); //Required: YYYY-MM-DD HH:MI:SS
	}
	
	//TBI: Some say this method of finding unique IDs is bad, due to unpredictable runtime. I say it is pure, as the result is truly random.
	public static function uniqueInjector(PDOStatement $query, array $arguments, string $uniqueKey, callable $generationCallback) : string|null {
		$attemptInjection = function() use($query, &$arguments, $uniqueKey, $generationCallback): null|string {
			try {
				$uniqueValue = $generationCallback();
				$arguments[$uniqueKey] = $uniqueValue; //Initialize/Update the new unique key to insert
				$query->execute($arguments);
				return $uniqueValue; //Return the unique key that is actually being used - to reference it later.
			} catch (PDOException $e) {
				//Validate, that the expected error happens (unique key constrain issue):
				if($e->getCode() != 23000) {
					throw $e;
				}
				if(!str_starts_with($e->getMessage(), 'SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry \'')) {
					throw $e;
				}
				return null;
			}
		};
		
		$startTime = microtime(true);
		
		//Try at least 5 times:
		for($i = 0; $i < 5; $i++) {
			$result = $attemptInjection();
			if($result !== null) {
				return $result;
			}
		}
		
		//If the duration exceeded half a second, stop - we do not want to flood the DB - or wait forever - something is wrong here!
		while((microtime(true) - $startTime) < 0.5) {
			$result = $attemptInjection();
			if($result !== null) {
				return $result;
			}
		};
		
		//Tried for longer than half a second, stop trying now.
		return null;
	}
}
