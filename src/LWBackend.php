<?php

namespace MP;

use MP\LWObjects\LWAuthor;
use MP\LWObjects\LWComment;

class LWBackend {
	public static function queryCommentsForPost(string $postID): MixedResult {
		$query = 'query GetComments($objid:String!){comments(objid:$objid){id body author{id username picture flair}}}';
		$queryResponse = self::queryFromLogicWorldBackend($query, [
			'objid' => $postID,
		]);
		if ($queryResponse->hasFailed()) {
			return new MixedResult(false, 'FAILED to execute request: ' . $queryResponse->getContent());
		}
		
		$objRoot = json_decode($queryResponse->getContent(), true);
		if ($objRoot === null) {
			//Not able to decode JSON, invalid?
			return new MixedResult(false, 'FAILED to parse JSON: ' . $queryResponse->getContent());
		}
		
		//Now we got something to work with:
		if (isset($objRoot['error'])) {
			//Failed to perform GraphQL query:
			return new MixedResult(false, 'FAILED to query: ' . $queryResponse->getContent());
		}
		
		//Closures:
		$errorMessage = '';
		$is_string_field_set = function ($object, $key) use($queryResponse, &$errorMessage) {
			if(!isset($object[$key])) {
				$errorMessage = 'Could not find string field ' . $key . ' in "' . $queryResponse->getContent() . '" specific: "' . json_encode($object) . '"';
				return false;
			}
			$value = $object[$key];
			if(!is_string($value)) {
				$errorMessage = 'String field ' . $key . ' is not of type string in "' . $queryResponse->getContent() . '" specific: "' . json_encode($object) . '"';
				return false;
			}
			return true;
		};
		$is_array_field_set = function ($object, $key) use($queryResponse, &$errorMessage) {
			if(!isset($object[$key])) {
				$errorMessage = 'Could not find array field ' . $key . ' in "' . $queryResponse->getContent() . '" specific: "' . json_encode($object) . '"';
				return false;
			}
			$value = $object[$key];
			if(!is_array($value)) {
				$errorMessage = 'Array field ' . $key . ' is not of type array in "' . $queryResponse->getContent() . '" specific: "' . json_encode($object) . '"';
				return false;
			}
			return true;
		};
		$is_unsigned_number_field_set = function ($object, $key) use($queryResponse, &$errorMessage) {
			if(!isset($object[$key])) {
				$errorMessage = 'Could not find number field ' . $key . ' in "' . $queryResponse->getContent() . '" specific: "' . json_encode($object) . '"';
				return false;
			}
			$value = $object[$key];
			if(!is_int($value)) {
				$errorMessage = 'Int field ' . $key . ' is not of type int in "' . $queryResponse->getContent() . '" specific: "' . json_encode($object) . '"';
				return false;
			}
			return true;
		};
		
		$comments = [];
		//Validation of succeeded content:
		if(!$is_array_field_set($objRoot, 'data')) {
			return new MixedResult(false, $errorMessage);
		}
		$objData = $objRoot['data'];
		if(!$is_array_field_set($objData, 'comments')) {
			return new MixedResult(false, $errorMessage);
		}
		$objComments = $objData['comments'];
		//Validate each comment:
		foreach ($objComments as $objComment) {
			if(!$is_string_field_set($objComment, 'id')) {
				return new MixedResult(false, $errorMessage);
			}
			if(!$is_string_field_set($objComment, 'body')) {
				return new MixedResult(false, $errorMessage);
			}
			if(!$is_array_field_set($objComment, 'author')) {
				return new MixedResult(false, $errorMessage);
			}
			$objAuthor = $objComment['author'];
			if(!$is_unsigned_number_field_set($objAuthor, 'id')) {
				return new MixedResult(false, $errorMessage);
			}
			if(!$is_string_field_set($objAuthor, 'username')) {
				return new MixedResult(false, $errorMessage);
			}
			if(!$is_string_field_set($objAuthor, 'picture')) {
				return new MixedResult(false, $errorMessage);
			}
			if(!$is_string_field_set($objAuthor, 'flair')) {
				return new MixedResult(false, $errorMessage);
			}
			$author = new LWAuthor($objAuthor['id'], $objAuthor['username'], $objAuthor['picture'], $objAuthor['flair']);
			$comment = new LWComment($objComment['id'], $objComment['body'], $author);
			$comments[] = $comment;
		}
		
		return new MixedResult(true, $comments);
	}
	
	public static function queryFromLogicWorldBackend(string $query, array $variables): MixedResult {
		return self::performGraphQueryLanguage('https://logicworld.net/graphql', $query, $variables);
	}
	
	public static function performGraphQueryLanguage(string $url, string $query, array $variables): MixedResult {
		$json = '{"query":"' . $query . '","variables":' . json_encode($variables) . '}';
		return self::doCurlPostJsonRequest($url, $json);
	}
	
	public static function doCurlPostJsonRequest(string $url, string $content): MixedResult {
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
			],
			CURLOPT_POSTFIELDS => $content,
		]);
		$curlResponse = curl_exec($ch);
		if($curlResponse === FALSE) {
			$content = curl_error($ch);
			curl_close($ch);
			return new MixedResult(false, $content);
		}
		curl_close($ch);
		return new MixedResult(true, $curlResponse);
	}
}
