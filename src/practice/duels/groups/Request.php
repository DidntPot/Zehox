<?php

namespace practice\duels\groups;

use JetBrains\PhpStorm\Pure;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;

class Request{
	/** @var int */
	private const MAX_WAIT_SECONDS = 120;

	/** @var string|null */
	private ?string $player;
	/** @var string|null */
	private ?string $requested;
	/** @var string */
	private string $queue;
	/** @var int */
	private int $secsFromRequest;

	/**
	 * @param        $requestor
	 * @param        $requested
	 * @param string $queue
	 */
	#[Pure] public function __construct($requestor, $requested, string $queue){
		if(!is_null(PracticeUtil::getPlayerName($requestor))){
			$this->player = PracticeUtil::getPlayerName($requestor);
		}

		if(!is_null(PracticeUtil::getPlayerName($requested))){
			$this->requested = PracticeUtil::getPlayerName($requested);
		}

		$this->secsFromRequest = 0;

		$this->queue = $queue;
	}

	/**
	 * @param PracticePlayer $p
	 * @param                $requestedPlayer
	 *
	 * @return bool
	 */
	public static function canSend(PracticePlayer $p, $requestedPlayer) : bool{
		$result = false;
		$msg = null;
		$requested = strval($requestedPlayer);

		if(PracticeCore::getPlayerHandler()->isPlayerOnline($requested)){
			$rq = PracticeCore::getPlayerHandler()->getPlayer($requested);
			if(!$rq->equals($p))
				$result = PracticeUtil::canRequestPlayer($p->getPlayer(), $rq);
			else $msg = PracticeUtil::getMessage("duels.misc.fail-yourself");

		}else{
			$msg = PracticeUtil::getMessage("not-online");
			$msg = strval(str_replace("%player-name%", $requested, $msg));
		}

		if(!is_null($msg)) $p->sendMessage($msg);

		return $result;
	}

	/**
	 * @return bool
	 */
	public function update() : bool{
		$this->secsFromRequest++;

		$delete = false;
		$max = self::MAX_WAIT_SECONDS;

		if($this->secsFromRequest >= $max){
			$delete = true;
		}elseif(!$this->isRequestedOnline() or !$this->isRequestorOnline())
			$delete = true;

		return $delete;
	}

	/**
	 * @return bool
	 */
	public function isRequestedOnline() : bool{
		return PracticeCore::getPlayerHandler()->isPlayerOnline($this->requested);
	}

	/**
	 * @return bool
	 */
	public function isRequestorOnline() : bool{
		return PracticeCore::getPlayerHandler()->isPlayerOnline($this->player);
	}

	/**
	 * @return string
	 */
	public function getQueue() : string{
		return $this->queue;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isTheRequestor($player) : bool{
		$result = false;
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){
			$p = PracticeCore::getPlayerHandler()->getPlayer($player);
			if($this->isRequestorOnline()){
				$result = $this->getRequestor()->equals($p);
			}
		}
		return $result;
	}

	/**
	 * @return PracticePlayer|null
	 */
	public function getRequestor() : ?PracticePlayer{
		return PracticeCore::getPlayerHandler()->getPlayer($this->player);
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isTheRequested($player) : bool{
		$result = false;
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){
			$p = PracticeCore::getPlayerHandler()->getPlayer($player);
			if($this->isRequestedOnline()){
				$result = $this->getRequestor()->equals($p);
			}
		}
		return $result;
	}

	/**
	 * @return void
	 */
	public function setExpired() : void{
		if($this->isRequestorOnline()){
			$p = $this->getRequestor();
			if(!$p->isInDuel() and !PracticeCore::getDuelHandler()->isWaitingForDuelToStart($p->getPlayer())){
				$p->sendMessage(PracticeUtil::str_replace(PracticeUtil::getMessage("duels.1vs1.result-msg"), ["%player%" => $this->getRequestedName(), "%accept%" => "declined", "%ranked% " => "", "%kit%" => $this->queue, "%msg%" => ""]));
			}
		}

		if($this->isRequestedOnline()){
			$p = $this->getRequested();
			if(!$p->isInDuel() and !PracticeCore::getDuelHandler()->isWaitingForDuelToStart($p->getPlayer())){
				$p->sendMessage(PracticeUtil::str_replace(PracticeUtil::getMessage("duels.1vs1.fail-no-time"), ["%player%" => $this->getRequestorName()]));
			}
		}
	}

	/**
	 * @return string
	 */
	public function getRequestedName() : string{
		return $this->requested;
	}

	/**
	 * @return PracticePlayer|null
	 */
	public function getRequested() : ?PracticePlayer{
		return PracticeCore::getPlayerHandler()->getPlayer($this->requested);
	}

	/**
	 * @return string
	 */
	public function getRequestorName() : string{
		return $this->player;
	}

	/**
	 * @return bool
	 */
	public function canAccept() : bool{
		$result = false;

		if($this->isRequestedOnline()){
			$msg = null;
			$requested = $this->getRequested();
			if($this->isRequestorOnline()){
				$player = $this->getRequestor();
				$result = !$requested->equals($player);
			}else{
				$msg = PracticeUtil::getMessage("not-online");
				$msg = strval(str_replace("%player-name%", $this->player, $msg));
			}

			if(!is_null($msg)) $requested->sendMessage($msg);
		}

		return $result;
	}

	/**
	 * @param $object
	 *
	 * @return bool
	 */
	#[Pure] public function equals($object) : bool{
		$result = false;

		if($object instanceof Request){

			$rqName = $object->getRequestedName();
			$plName = $object->getRequestorName();

			$result = $rqName === $this->requested and $plName === $this->player;
		}

		return $result;
	}
}