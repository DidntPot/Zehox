<?php

declare(strict_types=1);

namespace practice\duels;

use JetBrains\PhpStorm\Pure;
use pocketmine\player\Player;
use practice\arenas\DuelArena;
use practice\duels\groups\DuelGroup;
use practice\duels\groups\MatchedGroup;
use practice\duels\groups\QueuedPlayer;
use practice\PracticeCore;
use practice\PracticeUtil;
use practice\scoreboard\ScoreboardUtil;

class DuelHandler{
	/* @var QueuedPlayer[] */
	private array $queuedPlayers;

	/* @var MatchedGroup[] */
	private array $matchedGroups;

	/* @var DuelGroup[] */
	private array $duels;

	public function __construct(){
		$this->queuedPlayers = [];
		$this->matchedGroups = [];
		$this->duels = [];
	}

	// ------------------------------ QUEUE FUNCTIONS --------------------------------

	/**
	 * @param        $player
	 * @param string $queue
	 * @param bool   $isRanked
	 *
	 * @return void
	 */
	public function addPlayerToQueue($player, string $queue, bool $isRanked = false){
		$playerHandler = PracticeCore::getPlayerHandler();

		if($playerHandler->isPlayerOnline($player)){

			$p = $playerHandler->getPlayer($player);

			$name = $p->getPlayerName();

			$peOnly = $playerHandler->canQueuePEOnly($name);

			$newQueue = new QueuedPlayer($name, $queue, $isRanked, $peOnly);

			$ranked = ($isRanked ? "Ranked" : "Unranked");
			$arr = ["%ranked%" => $ranked, "%queue%" => $queue];

			$msg = PracticeUtil::str_replace(PracticeUtil::getMessage("duels.queue.enter"), $arr);

			$p->sendMessage($msg);

			PracticeCore::getItemHandler()->spawnQueueItems($p->getPlayer());

			if($this->isPlayerInQueue($p->getPlayerName()))
				unset($this->queuedPlayers[$p->getPlayerName()]);

			$this->queuedPlayers[$p->getPlayerName()] = $newQueue;

			$p->setSpawnScoreboard(true);

			ScoreboardUtil::updateSpawnScoreboards($p);
		}
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	#[Pure] public function isPlayerInQueue($player) : bool{
		$name = PracticeUtil::getPlayerName($player);

		return ($name !== null) and isset($this->queuedPlayers[$name]);
	}

	/**
	 * @return bool
	 */
	public function updateQueues() : bool{
		$result = false;

		$keys = array_keys($this->queuedPlayers);

		foreach($keys as $key){

			if(isset($this->queuedPlayers[$key])){

				$player = $this->queuedPlayers[$key];

				$pQueue = $player->getQueue();

				$remove = false;

				if($player->isPlayerOnline()){

					$p = $player->getPlayer();

					if($p->isInArena()){

						$ranked = ($player->isRanked ? "Ranked" : "Unranked");
						$arr = ["%ranked%" => $ranked, "%queue%" => $pQueue];

						$msg = PracticeUtil::getMessage("duels.queue.leave");
						$msg = PracticeUtil::str_replace($msg, $arr);

						$p->sendMessage($msg);

						$remove = true;
					}
				}else $remove = true;

				if($remove === true){
					$result = true;
					unset($this->queuedPlayers[$key]);
				}
			}
		}

		return $result;
	}

	/**
	 * @param string $queue
	 * @param bool   $ranked
	 *
	 * @return int
	 */
	#[Pure] public function getNumQueuedFor(string $queue, bool $ranked) : int{
		$result = 0;

		foreach($this->queuedPlayers as $aQueue){
			if($aQueue->getQueue() === $queue and $ranked === $aQueue->isRanked())
				$result++;
		}

		return $result;
	}

	/**
	 * @return int
	 */
	public function getNumberOfQueuedPlayers() : int{
		return count($this->queuedPlayers);
	}

	/**
	 * @return array
	 */
	public function getQueuedPlayers() : array{
		return $this->queuedPlayers;
	}

	/**
	 * @param             $player
	 * @param             $opponent
	 * @param bool        $isDirect
	 * @param string|null $queue
	 *
	 * @return void
	 */
	public function setPlayersMatched($player, $opponent, bool $isDirect = false, string $queue = null) : void{
		if(!$isDirect){

			$playerHandler = PracticeCore::getPlayerHandler();

			if($this->isPlayerInQueue($player) and $this->isPlayerInQueue($opponent)){

				$pQueue = $this->getQueuedPlayer($player);
				$oQueue = $this->getQueuedPlayer($opponent);

				$pName = $pQueue->getPlayerName();
				$oName = $oQueue->getPlayerName();

				$ranked = $pQueue->isRanked();
				$queue = $pQueue->getQueue();

				if($this->isAnArenaOpen($queue)){

					$p = $pQueue->getPlayer();
					$o = $oQueue->getPlayer();

					$str = ($ranked ? "Ranked" : "Unranked");

					$oppElo = $playerHandler->getEloFrom($pName, $queue);
					$pElo = $playerHandler->getEloFrom($oName, $queue);

					$msg = PracticeUtil::getMessage("duels.queue.found-match");
					$msg = PracticeUtil::str_replace($msg, ["%ranked%" => $str, "%queue%" => $queue]);
					$oppMsg = PracticeUtil::str_replace($msg, ["%elo%" => (($ranked) ? "$oppElo" : ""), "%player%" => $pName]);
					$pMsg = PracticeUtil::str_replace($msg, ["%elo%" => (($ranked) ? "$pElo" : ""), "%player%" => $oName]);

					$p->sendMessage($pMsg);
					$o->sendMessage($oppMsg);

					$group = new MatchedGroup($pName, $oName, $queue, $ranked);
					$this->matchedGroups[] = $group;

					unset($this->queuedPlayers[$pName], $this->queuedPlayers[$oName]);
				}
			}
		}else{

			if(!is_null($queue)){

				if($this->isPlayerInQueue($player)) $this->removePlayerFromQueue($player, true);
				if($this->isPlayerInQueue($opponent)) $this->removePlayerFromQueue($opponent, true);

				$group = new MatchedGroup($player, $opponent, $queue, false);
				$this->matchedGroups[] = $group;
			}
		}
	}

	/**
	 * @param $player
	 *
	 * @return QueuedPlayer|null
	 */
	#[Pure] public function getQueuedPlayer($player) : ?QueuedPlayer{
		$name = PracticeUtil::getPlayerName($player);
		$result = null;
		if($this->isPlayerInQueue($player))
			$result = $this->queuedPlayers[$name];
		return $result;
	}

	// ------------------------------ MATCHED PLAYER FUNCTIONS --------------------------------

	/**
	 * @param string $queue
	 *
	 * @return bool
	 */
	public function isAnArenaOpen(string $queue) : bool{
		return count($this->getOpenArenas($queue)) > 0;
	}

	/**
	 * @param string $queue
	 *
	 * @return array
	 */
	public function getOpenArenas(string $queue) : array{
		$result = [];

		$arenaHandler = PracticeCore::getArenaHandler();

		$arenas = $arenaHandler->getDuelArenas();

		foreach($arenas as $arena){
			$closed = $arenaHandler->isArenaClosed($arena->getName());
			if($closed === false){
				$hasKit = $arena->hasKit($queue);
				if($hasKit === true) $result[] = $arena;
			}
		}
		return $result;
	}

	/**
	 * @param      $player
	 * @param bool $sendMsg
	 *
	 * @return void
	 */
	public function removePlayerFromQueue($player, bool $sendMsg = false) : void{
		if($this->isPlayerInQueue($player)){

			$queue = $this->getQueuedPlayer($player);

			if($queue instanceof QueuedPlayer){

				$ranked = ($queue->isRanked() ? "Ranked" : "Unranked");
				$arr = ["%ranked%" => $ranked, "%queue%" => $queue->getQueue()];

				$msg = PracticeUtil::getMessage("duels.queue.leave");
				$msg = PracticeUtil::str_replace($msg, $arr);

				if($queue->isPlayerOnline() and $sendMsg){
					$p = $queue->getPlayer();
					$p->sendMessage($msg);
					PracticeCore::getItemHandler()->spawnHubItems($p, true);
				}
			}

			unset($this->queuedPlayers[$queue->getPlayerName()]);
		}
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	#[Pure] public function isWaitingForDuelToStart($player) : bool{
		return !is_null($this->getGroupFrom($player));
	}

	/**
	 * @param $player
	 *
	 * @return mixed|MatchedGroup|null
	 */
	#[Pure] public function getGroupFrom($player) : mixed{
		$str = PracticeUtil::getPlayerName($player);
		$result = null;
		if(!is_null($str)){
			foreach($this->matchedGroups as $group){
				if($group->getPlayerName() === $str or $group->getOpponentName() === $str){
					$result = $group;
					break;
				}
			}
		}
		return $result;
	}

	/**
	 * @param $player
	 *
	 * @return Player|null
	 */
	public function getMatchedPlayer($player) : ?Player{
		$opponent = null;

		if($this->didFindMatch($player)){

			$otherQueue = $this->findQueueMatch($player);

			if($otherQueue->isPlayerOnline())
				$opponent = $otherQueue->getPlayer()->getPlayer();
		}

		return $opponent;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function didFindMatch($player) : bool{
		return !is_null($this->findQueueMatch($player));
	}

	/**
	 * @param $player
	 *
	 * @return QueuedPlayer|null
	 */
	private function findQueueMatch($player) : ?QueuedPlayer{
		$opponent = null;

		if(isset($player) and $this->isPlayerInQueue($player)){

			$pQueue = $this->getQueuedPlayer($player);

			$checkForPEQueue = $pQueue->isPEOnly();

			foreach($this->queuedPlayers as $queue){

				$equals = $queue->equals($pQueue);

				if($equals !== true){

					if($pQueue->hasSameQueue($queue)){

						$found = false;

						if($checkForPEQueue === true){

							if($queue->getPlayer()->peOnlyQueue()) $found = true;

						}else{

							if($queue->isPEOnly()){

								$found = $pQueue->getPlayer()->peOnlyQueue();

							}else $found = true;
						}

						if($found === true){
							$opponent = $queue;
							break;
						}
					}
				}
			}
		}
		return $opponent;
	}

	/**
	 * @return array
	 */
	public function getAwaitingGroups() : array{
		return $this->matchedGroups;
	}

	/**
	 * @param MatchedGroup $group
	 *
	 * @return void
	 */
	public function startDuel(MatchedGroup $group) : void{
		$arena = $this->findRandomArena($group->getQueue());

		if(!is_null($arena) and $this->isValidMatched($group)){

			$index = $this->getMatchedIndexOf($group);

			if($group->isPlayerOnline() and $group->isOpponentOnline()){

				$duel = new DuelGroup($group, $arena->getName());

				PracticeCore::getArenaHandler()->setArenaClosed($arena->getName());
				$this->duels[] = $duel;
			}

			unset($this->matchedGroups[$index]);
			$this->matchedGroups = array_values($this->matchedGroups);
		}
	}

	/**
	 * @param string $queue
	 *
	 * @return mixed|DuelArena|null
	 */
	private function findRandomArena(string $queue) : mixed{
		$result = null;

		if($this->isAnArenaOpen($queue)){
			$openArenas = $this->getOpenArenas($queue);
			$count = count($openArenas);
			$rand = rand(0, $count - 1);
			$res = $openArenas[$rand];
			$result = $res;
		}

		return $result;
	}

	/**
	 * @param MatchedGroup $group
	 *
	 * @return bool
	 */
	#[Pure] private function isValidMatched(MatchedGroup $group) : bool{
		return $this->getMatchedIndexOf($group) !== -1;
	}

	// ------------------------------ DUEL PLAYER FUNCTIONS --------------------------------

	/**
	 * @param MatchedGroup $group
	 *
	 * @return int
	 */
	private function getMatchedIndexOf(MatchedGroup $group) : int{
		$index = array_search($group, $this->matchedGroups);
		if(is_bool($index) and $index === false)
			$index = -1;

		return $index;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isInDuel($player) : bool{
		return PracticeCore::getPlayerHandler()->isPlayer($player) and !is_null($this->getDuel($player));
	}

	/**
	 * @param      $object
	 * @param bool $isArena
	 *
	 * @return DuelGroup|null
	 */
	public function getDuel($object, bool $isArena = false) : ?DuelGroup{
		$result = null;

		$playerHandler = PracticeCore::getPlayerHandler();

		$arenaHandler = PracticeCore::getArenaHandler();

		if(isset($object) and !is_null($object)){
			if($isArena === false){
				if($playerHandler->isPlayer($object)){
					$p = $playerHandler->getPlayer($object);
					$pl = $p->getPlayer();
					foreach($this->duels as $duel){
						if($duel->isPlayer($pl) or $duel->isOpponent($pl)){
							$result = $duel;
							break;
						}
					}
				}
			}else{
				if(is_string($object) and $arenaHandler->isDuelArena($object)){
					$arena = $arenaHandler->getDuelArena($object);
					$name = $arena->getName();
					foreach($this->duels as $duel){
						$arenaName = $duel->getArenaName();
						if($arenaName === $name){
							$result = $duel;
							break;
						}
					}
				}
			}
		}
		return $result;
	}

	/**
	 * @param $arena
	 *
	 * @return bool
	 */
	public function isArenaInUse($arena) : bool{
		return !is_null($this->getDuel($arena, true));
	}

	/**
	 * @param DuelGroup $group
	 *
	 * @return void
	 */
	public function endDuel(DuelGroup $group){
		if($this->isValidDuel($group)){
			$index = $this->getDuelIndexOf($group);
			unset($this->duels[$index]);

		}else unset($group);

		$this->duels = array_values($this->duels);
	}

	/**
	 * @param DuelGroup $group
	 *
	 * @return bool
	 */
	#[Pure] private function isValidDuel(DuelGroup $group) : bool{
		return $this->getDuelIndexOf($group) !== -1;
	}

	/**
	 * @param DuelGroup $group
	 *
	 * @return int
	 */
	private function getDuelIndexOf(DuelGroup $group) : int{
		$index = array_search($group, $this->duels);
		if(is_bool($index) and $index === false)
			$index = -1;

		return $index;
	}

	/**
	 * @return array
	 */
	public function getDuelsInProgress() : array{
		return $this->duels;
	}

	/**
	 * @param string $queue
	 * @param bool   $ranked
	 *
	 * @return int
	 */
	#[Pure] public function getNumFightsFor(string $queue, bool $ranked) : int{
		$result = 0;

		foreach($this->duels as $duel){
			if($duel->getQueue() === $queue and $ranked === $duel->isRanked())
				$result += 2;
		}

		return $result;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isASpectator($player) : bool{
		$duel = $this->getDuelFromSpec($player);
		return !is_null($duel);
	}

	/**
	 * @param $spec
	 *
	 * @return null|DuelGroup
	 */
	public function getDuelFromSpec($spec) : ?DuelGroup{
		$result = null;

		$playerHandler = PracticeCore::getPlayerHandler();

		if($playerHandler->isPlayerOnline($spec)){
			$player = $playerHandler->getPlayer($spec);
			$name = $player->getPlayerName();
			foreach($this->duels as $duel){
				if($duel->isSpectator($name)){
					$result = $duel;
					break;
				}
			}
		}
		return $result;
	}
}