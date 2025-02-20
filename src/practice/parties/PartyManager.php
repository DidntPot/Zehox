<?php

declare(strict_types=1);

namespace practice\parties;

use JetBrains\PhpStorm\Pure;
use pocketmine\player\Player;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;

class PartyManager{
	/* @var PracticeParty[] */
	private array $parties;

	/* @var PartyInvite[] */
	private array $partyInvites;

	public function __construct(){
		$this->parties = [];
		$this->partyInvites = [];
	}

	/**
	 * @param Player      $player
	 * @param string|null $name
	 *
	 * @return void
	 */
	public function createParty(Player $player, string $name = null) : void{
		$party = new PracticeParty($player->getName(), $name);

		$this->parties[$party->getId()] = $party;

		$msg = PracticeUtil::getMessage('party.create.success');

		$player->sendMessage($msg);
	}

	/**
	 * @param PartyInvite $invite
	 *
	 * @return bool
	 */
	public function addPlayerToPartyFromInvite(PartyInvite $invite) : bool{
		$result = false;

		if(PracticeUtil::arr_contains_value($invite, $this->partyInvites)){

			$index = PracticeUtil::arr_indexOf($invite, $this->partyInvites);

			$invite = $this->partyInvites[$index];

			$requestor = $invite->getSender();

			$player = $invite->getInvited();

			if($this->isLeaderOFAParty($requestor) and PracticeCore::getPlayerHandler()->isPlayerOnline($player)){

				$p = PracticeCore::getPlayerHandler()->getPlayer($player);

				$party = $this->getPartyFromLeader($requestor);

				if(!$p->isInParty()){

					$party->addToParty($player);

					$result = true;
				}

				unset($this->partyInvites[$index]);
			}
		}

		return $result;
	}

	/**
	 * @param string $player
	 *
	 * @return bool
	 */
	public function isLeaderOFAParty(string $player) : bool{
		return !is_null($this->getPartyFromLeader($player));
	}

	/**
	 * @param string $leader
	 *
	 * @return null|PracticeParty
	 */
	public function getPartyFromLeader(string $leader) : ?PracticeParty{
		$result = null;

		$keys = array_keys($this->parties);
		foreach($keys as $key){
			$party = $this->parties[$key];
			if($party->isLeaderOfParty($leader)){
				$result = $party;
				break;
			}
		}

		return $result;
	}

	/**
	 * @param string $player
	 * @param bool   $kick
	 *
	 * @return bool
	 */
	public function removePlayerFromParty(string $player, bool $kick = false) : bool{
		$result = false;

		if($this->isPlayerInParty($player)){

			$result = true;

			$party = $this->getPartyFromPlayer($player);

			$remove = $party->removeFromParty($player, $kick);

			if($remove === true)
				unset($this->parties[$party->getId()]);
		}

		return $result;
	}

	/**
	 * @param string $player
	 *
	 * @return bool
	 */
	public function isPlayerInParty(string $player) : bool{
		return !is_null($this->getPartyFromPlayer($player));
	}

	/**
	 * @param string $player
	 *
	 * @return null|PracticeParty
	 */
	public function getPartyFromPlayer(string $player) : ?PracticeParty{
		$result = null;

		$keys = array_keys($this->parties);

		foreach($keys as $key){
			$party = $this->parties[$key];
			if($party->isInParty($player)){
				$result = $party;
				break;
			}
		}

		return $result;
	}

	/**
	 * @param PracticePlayer $sender
	 * @param string         $player
	 *
	 * @return void
	 */
	public function invitePlayer(PracticePlayer $sender, string $player) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player) and $sender->isInParty()){

			if(!$this->hasPendingInvite($sender->getPlayerName(), $player)){

				$p = PracticeCore::getPlayerHandler()->getPlayer($player);

				$invite = new PartyInvite($sender->getPlayerName(), $player);

				$n = $sender->getPlayerName();
				$msg = PracticeUtil::str_replace(PracticeUtil::getMessage('party.invite.invited-msg'), ['%player%' => $n]);

				$p->sendMessage($msg);

				$msg = PracticeUtil::str_replace(PracticeUtil::getMessage('party.invite.sender-msg'), ['%player%' => $p->getPlayerName()]);

				$sender->sendMessage($msg);

				$this->partyInvites[$invite->getID()] = $invite;

			}else{

				$msg = PracticeUtil::str_replace(PracticeUtil::getMessage('party.invite.pending-inv'), ['%player%' => $player]);

				$sender->sendMessage($msg);
			}
		}
	}

	/**
	 * @param string $sender
	 * @param string $invited
	 *
	 * @return bool
	 */
	#[Pure] public function hasPendingInvite(string $sender, string $invited) : bool{
		return !is_null($this->getPendingInvite($sender, $invited));
	}

	/**
	 * @param string $sender
	 * @param string $invited
	 *
	 * @return null|PartyInvite
	 */
	#[Pure] public function getPendingInvite(string $sender, string $invited) : ?PartyInvite{
		$result = null;

		$keys = array_keys($this->partyInvites);

		foreach($keys as $key){

			$invite = $this->partyInvites[$key];

			if($invite->isSameInvite($sender, $invited)){
				$result = $invite;
				break;
			}
		}
		return $result;
	}

	/**
	 * @return void
	 */
	public function updateInvites() : void{
		$size = count($this->partyInvites);
		$keys = array_keys($this->partyInvites);

		for($i = $size - 1; $i > -1; $i--){
			$key = $keys[$i];
			if(isset($this->partyInvites[$key])){
				$invite = $this->partyInvites[$key];
				if($invite->update()) unset($this->partyInvites[$key]);
			}
		}
	}
}