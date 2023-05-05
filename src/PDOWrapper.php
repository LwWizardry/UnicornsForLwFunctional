<?php

namespace MP;

use DateTime;
use DateTimeZone;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\DeleteBuilder;
use PDO;
use PDOException;
use Throwable;

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
	
	public static function deleteByID(string $table, int $id): void {
		(new DeleteBuilder($table))
			->whereValue('id', $id)
			->execute();
	}
	
	public static function deleteByIDSafe(string $table, int $id): void {
		try {
			self::deleteByID($table, $id);
		} catch (Throwable) {
			//Do nothing, as this call is already part of a 'catch' section and run as cleanup.
			//TODO: Forward errors to an error handler.
		}
	}
}
