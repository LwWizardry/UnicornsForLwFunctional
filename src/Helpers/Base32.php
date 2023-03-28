<?php

namespace MP\Helpers;

class Base32 {
	//Source code copied from https://github.com/bbars/utils/blob/master/php-base32-encode-decode/Base32.php
	// License at time of copy: MIT
	// Minor modifications.
	const BITS_5_RIGHT = 31;
	const CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'; // lower-case
	
	public static function encode(string $data, bool $padRight = false): string {
		$dataSize = strlen($data);
		$res = '';
		$remainder = 0;
		$remainderSize = 0;
		
		for ($i = 0; $i < $dataSize; $i++) {
			$b = ord($data[$i]);
			$remainder = ($remainder << 8) | $b;
			$remainderSize += 8;
			while ($remainderSize > 4) {
				$remainderSize -= 5;
				$c = $remainder & (self::BITS_5_RIGHT << $remainderSize);
				$c >>= $remainderSize;
				$res .= static::CHARS[$c];
			}
		}
		if ($remainderSize > 0) {
			// remainderSize < 5:
			$remainder <<= (5 - $remainderSize);
			$c = $remainder & self::BITS_5_RIGHT;
			$res .= static::CHARS[$c];
		}
		if ($padRight) {
			$padSize = (8 - ceil(($dataSize % 5) * 8 / 5)) % 8;
			$res .= str_repeat('=', $padSize);
		}
		
		return $res;
	}
}
