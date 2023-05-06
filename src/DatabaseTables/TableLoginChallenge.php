<?php

namespace MP\DatabaseTables;

use MP\ErrorHandling\BadRequestException;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\QueryBuilder;
use MP\Helpers\UniqueInjectorHelper;
use MP\LwApi\LWAuthor;
use MP\PDOWrapper;
use Throwable;

class TableLoginChallenge {
	public static function deleteOutdated(): void {
		QueryBuilder::delete('login_challenges')
			->whereOlderThanHours('creation_time', 1)
			->execute();
	}
	
	public static function getChallengeForSession(string $sessionID): null|TableLoginChallenge {
		$challenge_entry = QueryBuilder::select('login_challenges')
			->whereValue('session', $sessionID)
			->whereNewerThanHours('creation_time', 1)
			->expectOneRow()
			->execute();
		if ($challenge_entry === false) {
			//Did not find the session ID.
			return null;
		}
		if ($challenge_entry['challenge'] == null) {
			throw new InternalDescriptiveException('The login session being used had no challenge set. This should be impossible.');
		}
		
		return new TableLoginChallenge($challenge_entry['session'], $challenge_entry['challenge'], $challenge_entry);
	}
	
	public static function generateNewChallenge(): TableLoginChallenge {
		$challengeID = QueryBuilder::insert('login_challenges')
			->setUTC('creation_time')
			->return('id')
			->execute();
		
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
		
		return new TableLoginChallenge($session, $challenge, null);
	}
	
	//Object:
	
	private null|int $id; //Can be null, only the case after generation. Rather use session as identifier.
	private string $session;
	private string $challenge;
	private string $createdAt;
	private null|LWAuthor $author; //Is null, if the challenge was never sent via comment
	
	//TODO: Eventually make this constructor or in general the creation of this class more neat.
	// Although right now it is only private.
	private function __construct(string $session, string $challenge, null|array $raw_data) {
		$this->session = $session;
		$this->challenge = $challenge;
		if ($raw_data !== null) {
			$this->id = $raw_data['id'];
			$this->createdAt = $raw_data['creation_time'];
			
			$hasAuthor = $raw_data['lw_name'] != null;
			if ($hasAuthor !== ($raw_data['lw_id'] != null)) {
				//ID was set without the name, or name was set without ID:
				throw new InternalDescriptiveException('LoginChallenge has corrupted lw-user entry (either Name/ID is missing): [Name: "' . $raw_data['lw_name'] . '", ID: "' . $raw_data['lw_id'] . '", Picture: "' . $raw_data['lw_picture'] . '", Flair: "' . $raw_data['lw_flair'] . '"');
			}
			if ($hasAuthor) {
				//Parse:
				$this->author = new LWAuthor(
					$raw_data['lw_id'],
					$raw_data['lw_name'],
					$raw_data['lw_picture'],
					$raw_data['lw_flair']
				);
			} else if (($raw_data['lw_picture'] != null) || ($raw_data['lw_flair'] != null)) {
				//No name/ID was set, but picture or flair was set... How?
				throw new InternalDescriptiveException('LoginChallenge has corrupted lw-user entry. Name/ID are not set, yet there is a picture/flair entry: "' . $raw_data['lw_picture'] . '"/"' . $raw_data['lw_flair'] . '"');
			}
		}
	}
	
	public function getSession(): string {
		return $this->session;
	}
	
	public function getChallenge(): string {
		return $this->challenge;
	}
	
	public function updateWithAuthor(LWAuthor $author): void {
		$this->author = $author;
		QueryBuilder::update('login_challenges')
			->setValues([
				'lw_id' => $this->author->getId(),
				'lw_name' => $this->author->getUsername(),
				'lw_picture' => $this->author->getPicture(),
				'lw_flair' => $this->author->getFlair(),
			])
			->whereValue('session', $this->session)
			->execute();
	}
	
	public function hasAuthor(): bool {
		return $this->author !== null;
	}
	
	public function getAuthor(): null|LWAuthor {
		return $this->author;
	}
	
	public function getCreatedAt(): string {
		return $this->createdAt;
	}
	
	public function delete(bool $safe = false): void {
		if($safe) {
			PDOWrapper::deleteByIDSafe('login_challenges', $this->id);
		} else {
			PDOWrapper::deleteByID('login_challenges', $this->id);
		}
	}
}
