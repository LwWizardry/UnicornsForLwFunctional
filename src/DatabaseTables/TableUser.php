<?php

namespace MP\DatabaseTables;

use MP\DatabaseTables\Generic\Fetchable;
use MP\ErrorHandling\BadRequestException;
use MP\ErrorHandling\InternalDescriptiveException;
use MP\Helpers\QueryBuilder\Queries\SelectBuilder;
use MP\Helpers\QueryBuilder\QueryBuilder as QB;
use MP\Helpers\UniqueInjectorHelper;
use MP\PDOWrapper;
use Throwable;

class TableUser {
	public static function createEmpty(string $acceptedPPAt): null|self {
		$result = QB::insert('users')
			->setUTC('created_at')
			->setValue('privacy_policy_accepted_at', $acceptedPPAt)
			->return('id', 'created_at')
			->execute();
		$id = $result['id'];
		$created_at = $result['created_at'];
		
		try {
			$identifier = UniqueInjectorHelper::shortIdentifier('users', $id);
		} catch (Throwable $e) {
			PDOWrapper::deleteByIDSafe('users', $id);
			throw $e;
		}
		
		return new self($id, $identifier, $created_at, $acceptedPPAt, null);
	}
	
	//Used to add or edit mods
	// > No need for private information
	public static function fromSession($authToken): self {
		$result = QB::select('sessions')
			->selectColumn('id')
			->whereValue('token', $authToken)
			->joinThis('user', self::getBuilder())
			->expectOneRow()
			->execute();
		if($result === false) {
			throw new BadRequestException('Invalid auth token');
		}
		
		//TBI: Side effect, should be handled differently.
		//Update timestamp of token:
		QB::update('sessions')
			->setUTC('last_usage_at')
			->whereValue('id', $result['sessions.id'])
			->execute();
		
		return self::fromDB($result);
	}
	
	//Used to fetch mods for a user.
	// > No need for private information.
	public static function fromIdentifier(string $identifier): null|TableUser {
		$result = self::getBuilder()
			->whereValue('identifier', $identifier)
			->expectOneRow()
			->execute();
		if($result === false) {
			return null;
		}
		return self::fromDB($result, prefix: '');
	}
	
	public static function getBuilder(bool $privateData = false, bool $fetchUsername = false, bool $fetchLinkage = false): SelectBuilder {
		$query = QB::select('users', 'pU')
			->selectColumn('id', 'identifier');
		if($privateData) {
			$query->selectColumn('created_at', 'privacy_policy_accepted_at');
		}
		if($fetchUsername || $fetchLinkage) {
			$query->joinThat('user', TableLWUser::getBuilder(otherData: $fetchLinkage), type: 'LEFT');
		}
		return $query;
	}
	
	public static function fromDB(
		array $columns, string $prefix = 'users.',
		bool $privateData = false, bool $fetchUsername = false, bool $fetchLinkage = false
	): self {
		$identifier = $columns[$prefix. 'identifier'];
		if($identifier === null) {
			//Due to the generation process of a user (injection of identifier), null is a valid entry.
			throw new InternalDescriptiveException('Tried to access incomplete user. By design this should never happen. (Fields are null).');
		}
		return new self(
			$columns[$prefix. 'id'],
			$identifier,
			$privateData ? $columns[$prefix. 'created_at'] : Fetchable::i(),
			$privateData ? $columns[$prefix. 'privacy_policy_accepted_at'] : Fetchable::i(),
			$fetchUsername || $fetchLinkage ? TableLWUser::fromDB($columns, otherData: $fetchLinkage) : Fetchable::i(),
		);
	}
	
	private int $dbID;
	private string $identifier;
	private Fetchable|string $createdAt;
	private Fetchable|string $acceptedPrivacyPolicyAt;
	private Fetchable|null|TableLWUser $lwLinkage;
	
	private function __construct(int $dbID, string $identifier, Fetchable|string $createdAt, Fetchable|string $acceptedPrivacyPolicyAt, Fetchable|null|TableLWUser $lwLinkage) {
		$this->dbID = $dbID;
		$this->identifier = $identifier;
		$this->createdAt = $createdAt;
		$this->acceptedPrivacyPolicyAt = $acceptedPrivacyPolicyAt;
		$this->lwLinkage = $lwLinkage;
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
	public function getIdentifier(): string {
		return $this->identifier;
	}
	
	/**
	 * @return Fetchable|string
	 */
	public function getCreatedAt(): Fetchable|string {
		return $this->createdAt;
	}
	
	/**
	 * @return Fetchable|string
	 */
	public function getAcceptedPrivacyPolicyAt(): Fetchable|string {
		return $this->acceptedPrivacyPolicyAt;
	}
	
	/**
	 * @return null|TableLWUser
	 */
	public function getLwLinkage(): null|TableLWUser {
		if($this->lwLinkage === Fetchable::i()) {
			throw new InternalDescriptiveException('Attempted to use LwLinkage, but it was not fetched yet!');
		}
		return $this->lwLinkage;
	}
	
	/**
	 * @return TableLWUser
	 */
	public function getLwLinkageNonNull(): TableLWUser {
		if($this->lwLinkage === Fetchable::i()) {
			throw new InternalDescriptiveException('Attempted to use LwLinkage, but it was not fetched yet!');
		}
		if($this->lwLinkage === null) {
			throw new InternalDescriptiveException('Attempted to use LwLinkage, but it was null! It should not be.');
		}
		return $this->lwLinkage;
	}
	
	public function deletePrototype(bool $safe = false): void {
		if($safe) {
			PDOWrapper::deleteByIDSafe('users', $this->dbID);
		} else {
			PDOWrapper::deleteByID('users', $this->dbID);
		}
	}
	
	public function updateAcceptPPAt(string $acceptedPPAt): void {
		QB::update('users')
			->setValue('privacy_policy_accepted_at', $acceptedPPAt)
			->whereValue('id', $this->dbID)
			->whereValue('privacy_policy_accepted_at', $acceptedPPAt, '<')
			->execute();
	}
	
	//May only be used in the creation process.
	public function injectLinkage(TableLWUser $lwUser): void {
		$this->lwLinkage = $lwUser;
	}
	
	//Implies public data only:
	public function asFrontEndJSON(): array {
		return [
			'identifier' => $this->identifier,
			'lw_data' => $this->getLwLinkage()?->asFrontEndJSON(),
		];
	}
}
