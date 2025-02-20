<?php

declare(strict_types=1);

namespace practice;

use pocketmine\scheduler\Task;
use pocketmine\utils\TextFormat;
use practice\duels\groups\DuelGroup;

class PracticeTask extends Task{
	/** @var int */
	private const int MAX_ANNOUNCEMENT_TIME = 45;

	/** @var PracticeCore */
	private PracticeCore $core;
	/** @var int|float */
	private int|float $seconds = 60 * 60 * 3;
	/** @var int */
	private int $announcementTime = 0;
	/** @var string[] */
	private array $announcements;
	/** @var int */
	private int $updateLeaderboardsTime;

	/**
	 * PracticeTask constructor.
	 *
	 * @param PracticeCore $core
	 */
	public function __construct(PracticeCore $core){
		$this->core = $core;
		$this->announcements = [
			TextFormat::AQUA . 'See a hacker online? Use ' . TextFormat::YELLOW . '/report player' . TextFormat::AQUA . ' to notify the staff of hackers on the server.',
			TextFormat::AQUA . 'Find a bug on the server? Use ' . TextFormat::YELLOW . '/report bug' . TextFormat::AQUA . ' to notify the staff of bugs on the server.',
			TextFormat::AQUA . 'Is a staff abusing or doing any other misconduct? Use ' . TextFormat::YELLOW . '/report staff' . TextFormat::AQUA . ' to notify the owner of abusing staff.'
		];
		$this->updateLeaderboardsTime = (PracticeUtil::isMysqlEnabled() ? PracticeUtil::minutesToTicks(20) : PracticeUtil::secondsToTicks(5));
	}

	/**
	 * @return void
	 */
	public function onRun() : void{
		$currentTick = 0;
		$currentTick--;

		if($currentTick % 20 === 0 and $currentTick !== 0) $this->seconds--;

		$this->broadcastAnnouncement($currentTick);
		$this->updateDuels($currentTick);
		$this->updatePlayers($currentTick);
		$this->checkForReload($currentTick);

		if($currentTick % 20 === 0 and $currentTick !== 0) PracticeCore::getPartyManager()->updateInvites();
		if($currentTick % $this->updateLeaderboardsTime === 0 and $currentTick !== 0 and !PracticeUtil::isMysqlEnabled()) PracticeCore::getPlayerHandler()->updateLeaderboards();
	}

	/**
	 * @param int $currentTick
	 *
	 * @return void
	 */
	private function broadcastAnnouncement(int $currentTick) : void{
		$server = $this->core->getServer();
		if($this->announcementTime > self::MAX_ANNOUNCEMENT_TIME){
			$server->broadcastMessage(
				PracticeUtil::getMessage('broadcast-msg') . "\n" . $this->announcements[rand(0, 2)]
			);
			$this->announcementTime = 0;
		}
		if($currentTick % 20 === 0) $this->announcementTime++;
	}

	/**
	 * @param int $currentTick
	 *
	 * @return void
	 */
	private function updateDuels(int $currentTick) : void{
		$duelHandler = PracticeCore::getDuelHandler();
		if($currentTick % 20 === 0) PracticeCore::get1vs1Handler()->update();

		$queuedPlayers = $duelHandler->getQueuedPlayers();
		$awaitingMatches = $duelHandler->getAwaitingGroups();
		$duels = $duelHandler->getDuelsInProgress();
		$keys = array_keys($queuedPlayers);

		foreach($keys as $key){
			if(isset($queuedPlayers[$key])){
				$queue = $queuedPlayers[$key];
				$name = $queue->getPlayerName();

				if($queue->isPlayerOnline() and $duelHandler->didFindMatch($name)){
					$opponent = $duelHandler->getMatchedPlayer($name);
					$duelHandler->setPlayersMatched($name, $opponent);
				}
			}
		}

		foreach($awaitingMatches as $match){
			$queue = $match->getQueue();
			if($duelHandler->isAnArenaOpen($queue)) $duelHandler->startDuel($match);
		}

		foreach($duels as $duel){
			if($duel instanceof DuelGroup) $duel->update();
		}
	}

	/**
	 * @param int $currentTick
	 *
	 * @return void
	 */
	private function updatePlayers(int $currentTick) : void{

		$playerHandler = PracticeCore::getPlayerHandler();

		$update = $currentTick % 20 === 0;

		$players = $playerHandler->getOnlinePlayers();

		foreach($players as $player){
			$player->updateNoDmgTicks();

			if($update === true) $player->updatePlayer();
		}
	}

	/**
	 * @param int $currentTick
	 *
	 * @return void
	 */
	private function checkForReload(int $currentTick) : void{

		$server = $this->core->getServer();
		$message = "[Server] Server restarting in ";

		if($this->seconds < 0){
			$server->shutdown();
		}elseif($this->seconds < 10){
			if($currentTick % 20 === 0) PracticeUtil::broadcastMsg($message . "$this->seconds second(s).");
		}elseif($this->seconds == 60 or $this->seconds == 60 * 2 or $this->seconds == 60 * 5 or
			$this->seconds == 60 * 10 or $this->seconds == 60 * 15){
			if($currentTick % 20 === 0) PracticeUtil::broadcastMsg($message . $this->seconds / 60 . " minute(s).");
		}
	}
}