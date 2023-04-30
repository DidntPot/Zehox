<?php

declare(strict_types=1);

namespace practice\parties;

use practice\PracticeCore;
use practice\PracticeUtil;

class PartyInvite{
	/** @var int */
	private const MAX_INVITE_SECS = 20;
	/** @var int */
	private static int $INVITEIDS = 0;

	/** @var string */
	private string $sender;
	/** @var string */
	private string $invited;
	/** @var int */
	private int $seconds;
	/** @var int */
	private int $id;

	/**
	 * @param string $sender
	 * @param string $invited
	 */
	public function __construct(string $sender, string $invited){
		$this->sender = $sender;
		$this->invited = $invited;
		$this->seconds = 0;
		$this->id = self::$INVITEIDS;
		self::$INVITEIDS++;
	}

	/**
	 * @return int
	 */
	public function getID() : int{
		return $this->id;
	}

	/**
	 * @return string
	 */
	public function getSender() : string{
		return $this->sender;
	}

	/**
	 * @return string
	 */
	public function getInvited() : string{
		return $this->invited;
	}

	/**
	 * @return bool
	 */
	public function update() : bool{
		$remove = false;

		if($this->seconds > self::MAX_INVITE_SECS or !$this->arePlayersOnline()){
			$remove = true;
			$this->setExpired();
		}

		$this->seconds++;
		return $remove;
	}

	/**
	 * @return bool
	 */
	private function arePlayersOnline() : bool{
		return $this->isSenderOnline() and $this->isInvitedOnline();
	}

	/**
	 * @return bool
	 */
	private function isSenderOnline() : bool{
		return PracticeCore::getPlayerHandler()->isPlayerOnline($this->sender);
	}

	/**
	 * @return bool
	 */
	private function isInvitedOnline() : bool{
		return PracticeCore::getPlayerHandler()->isPlayerOnline($this->invited);
	}

	/**
	 * @return void
	 */
	private function setExpired() : void{
		$senderMsg = PracticeUtil::str_replace(PracticeUtil::getMessage("party.invite.expired-sender"), ["%player%" => $this->invited]);
		$invitedMsg = PracticeUtil::str_replace(PracticeUtil::getMessage("party.invite.expired-no-time"), ["%player%" => $this->sender]);

		if($this->isSenderOnline()) PracticeCore::getPlayerHandler()->getPlayer($this->sender)->sendMessage($senderMsg);
		if($this->isInvitedOnline()) PracticeCore::getPlayerHandler()->getPlayer($this->invited)->sendMessage($invitedMsg);
	}

	/**
	 * @param string $player
	 *
	 * @return bool
	 */
	public function canAcceptRequest(string $player) : bool{
		$result = false;
		if($player === $this->invited and $this->arePlayersOnline()){
			$p = PracticeCore::getPlayerHandler()->getPlayer($this->sender);
			if($p->isInParty() and PracticeCore::getPartyManager()->isLeaderOFAParty($this->sender)) $result = true;
		}

		return $result;
	}

	/**
	 * @param string $player
	 *
	 * @return bool
	 */
	public function isInvitedPlayer(string $player) : bool{
		return $this->invited === $player;
	}

	/**
	 * @param string $sender
	 * @param string $invited
	 *
	 * @return bool
	 */
	public function isSameInvite(string $sender, string $invited) : bool{
		return $this->sender === $sender and $this->invited === $invited;
	}
}