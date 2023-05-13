<?php

namespace MP\Helpers;

use MP\ErrorHandling\InternalDescriptiveException;

class FileSystemHelper {
	public static function checkFreeSpace(string $targetFolder): void {
		$freeSpace = disk_free_space($targetFolder);
		if($freeSpace === false) {
			throw new InternalDescriptiveException('Could not check disk space!');
		}
		if($freeSpace < $_ENV['FREE_SPACE']) {
			throw new InternalDescriptiveException('Disk is too full, cannot store more files!');
		}
	}
}
