<?php

namespace MP\DatabaseTables;

use Exception;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\QueryBuilder as QB;
use MP\Helpers\UniqueInjectorHelper;
use MP\LwApi\LWAuthor;
use MP\PDOWrapper;
use Throwable;

class TableLoginChallenge {
	public static function deleteOutdated(): void {
		QB::delete('login_challenges')
			->whereOlderThanHours('creation_time', 1)
			->execute();
	}
	
	public static function getChallengeForSession(string $sessionID): null|self {
		$challenge_entry = QB::select('login_challenges')
			->whereValue('session', $sessionID)
			->whereNewerThanHours('creation_time', 1)
			->expectOneRow()
			->execute();
		if ($challenge_entry === false) {
			//Did not find the session ID.
			return null;
		}
		return self::fromDB($challenge_entry, prefix: '');
	}
	
	public static function generateNewChallenge(): self {
		$result = QB::insert('login_challenges')
			->setUTC('creation_time')
			->return('id', 'creation_time')
			->execute();
		$challengeID = $result['id'];
		$creationTime = $result['creation_time'];
		
		//Generate
		$generateRandomChallenge = function (): string {
			$templates = [
				'Super mega nice challenge with ID {0} that will do for the {1}\'s test!',
				//'{0} Nice login challenge into the mod portal. With number {1} lel.',
				'{0} discover a place full of surprises. With {1} tree features.',
				//'It has {0} mods! (I wish). And I wanna check the portal out. Soon it has {1} mods!',
				'It has {0} features! (or so). And I wanna check that place out. Soon it has {1} features!',
			];
			$template_index = random_int(0, count($templates) - 1);
			$random_one = random_int(1, 999999999);
			$random_two = random_int(1, 999999999);
			$challenge = $templates[$template_index];
			$challenge = str_replace('{0}', $random_one, $challenge);
			return str_replace('{1}', $random_two, $challenge);
		};
		
		try {
			$session = UniqueInjectorHelper::largeIdentifier('login_challenges', $challengeID, 'session');
			$challenge = UniqueInjectorHelper::inject('login_challenges', $challengeID, 'challenge', $generateRandomChallenge);
		} catch (Throwable $e) {
			PDOWrapper::deleteByIDSafe('login_challenges', $challengeID);
			throw $e;
		}
		
		return new self($challengeID, $session, $challenge, $creationTime, null);
	}
	
	public static function fromDB(array $columns, string $prefix = 'login_challenges.'): null|self {
		$session = $columns[$prefix . 'session'];
		$challenge = $columns[$prefix . 'challenge'];
		if($session === null || $challenge === null) {
			throw new InternalDescriptiveException('Tried to access incomplete login challenge. By design this should never happen. (Fields are null).');
		}
		$dbID = $columns[$prefix . 'id'];
		$createdAt = $columns[$prefix . 'creation_time'];
		
		$lwName = $columns[$prefix . 'lw_name'];
		$lwIdentifier = $columns[$prefix . 'lw_id'];
		$lwPicture = $columns[$prefix . 'lw_picture'];
		$lwFlair = $columns[$prefix . 'lw_flair'];
		if(
			($lwName === null && ($lwIdentifier !== null || $lwPicture !== null || $lwFlair !== null))
			|| ($lwName !== null && ($lwIdentifier === null))
		) {
			//If name is 'null' all other fields must be 'null' too!
			//If name is not 'null', identifier must not be 'null' either!
			throw new InternalDescriptiveException('LoginChallenge has corrupted lw-user entry (either Name/ID is missing): ' .
			'[Name: "' . $lwName . '", ID: "' . $lwIdentifier . '", Picture: "' . $lwPicture . '", Flair: "' . $lwFlair . '"');
		}
		$linkage = $lwName === null ? null : new LWAuthor($lwIdentifier, $lwName, $lwPicture, $lwFlair);
		
		return new self(
			$dbID,
			$session,
			$challenge,
			$createdAt,
			$linkage,
		);
	}
	
	//Object:
	
	private int $dbID;
	private string $session;
	private string $challenge;
	private string $createdAt;
	private null|LWAuthor $author; //Is null, if the challenge was never sent via comment
	
	private function __construct(int $dbID, string $session, string $challenge, string $createdAt, null|LWAuthor $linkage) {
		$this->dbID = $dbID;
		$this->session = $session;
		$this->challenge = $challenge;
		$this->createdAt = $createdAt;
		$this->author = $linkage;
	}
	
	/**
	 * @return int
	 */
	public function getDbID(): int {
		return $this->dbID;
	}
	
	/**
	 * @return string
	 */
	public function getSession(): string {
		return $this->session;
	}
	
	/**
	 * @return string
	 */
	public function getChallenge(): string {
		return $this->challenge;
	}
	
	/**
	 * @return string
	 */
	public function getCreatedAt(): string {
		return $this->createdAt;
	}
	
	public function hasAuthor(): bool {
		return $this->author !== null;
	}
	
	/**
	 * @return LWAuthor
	 */
	public function getAuthor(): LWAuthor {
		if($this->author === null) {
			throw new Exception('Attempted to get author of login challenge, but it was not set yet. Call hasAuthor() first!');
		}
		return $this->author;
	}
	
	public function updateWithAuthor(LWAuthor $author): void {
		$this->author = $author;
		QB::update('login_challenges')
			->setValues([
				'lw_id' => $author->getId(),
				'lw_name' => $author->getUsername(),
				'lw_picture' => $author->getPicture(),
				'lw_flair' => $author->getFlair(),
			])
			->whereValue('id', $this->dbID)
			->execute();
	}
	
	public function delete(bool $safe = false): void {
		if($safe) {
			PDOWrapper::deleteByIDSafe('login_challenges', $this->dbID);
		} else {
			PDOWrapper::deleteByID('login_challenges', $this->dbID);
		}
	}
}
