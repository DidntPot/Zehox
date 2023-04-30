<?php

declare(strict_types=1);

namespace practice\duels\groups;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use pocketmine\block\Block;
use pocketmine\block\BlockLegacyIds;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;
use practice\arenas\DuelArena;
use practice\duels\misc\DuelInvInfo;
use practice\duels\misc\DuelPlayerHit;
use practice\duels\misc\DuelSpectator;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;
use practice\scoreboard\ScoreboardUtil;

class DuelGroup{
	/** @var string */
	public const NONE = "None";
	/** @var string */
	private const NO_SPEC_MSG = "spectators.none";

	/** @var int */
	public const MAX_COUNTDOWN_SEC = 5;
	/** @var int */
	public const MAX_DURATION_MIN = 30;
	/** @var int */
	public const MAX_END_DELAY_SEC = 1;

	/** @var string */
	private string $playerName;
	/** @var string */
	private string $opponentName;
	/** @var string */
	private string $arenaName;
	/** @var string */
	private string $winnerName;
	/** @var string */
	private string $loserName;

	/** @var string */
	private string $queue;

	/** @var string */
	private string $origOppTag;
	/** @var string */
	private string $origPlayerTag;

	/** @var int */
	private int $currentTick;
	/** @var int */
	private int $countdownTick;
	/** @var int */
	private int $endTick;

	/** @var bool */
	private bool $ranked;

	/** @var bool */
	private bool $started;
	/** @var bool */
	private bool $ended;

	/* @var DuelSpectator[] */
	private array $spectators;

	/** @var array */
	private array $blocks;

	/* @var DuelPlayerHit[] */
	private array $playerHits;

	/* @var DuelPlayerHit[] */
	private array $oppHits;

	/** @var int */
	private int $fightingTick;

	/** @var mixed */
	private mixed $arena;

	/** @var int */
	private int $opponentDevice;
	/** @var int */
	private int $playerDevice;

	/** @var int */
	private int $maxCountdownTicks;

	/**
	 * @param MatchedGroup $group
	 * @param string       $arena
	 */
	public function __construct(MatchedGroup $group, string $arena){
		$this->playerName = $group->getPlayerName();
		$this->opponentName = $group->getOpponentName();

		$duelHandler = PracticeCore::getDuelHandler();

		if($duelHandler->isASpectator($this->playerName)){
			$duel = $duelHandler->getDuelFromSpec($this->playerName);
			$duel->removeSpectator($this->playerName);
		}

		if($duelHandler->isASpectator($this->opponentName)){
			$duel = $duelHandler->getDuelFromSpec($this->opponentName);
			$duel->removeSpectator($this->opponentName);
		}

		$this->winnerName = self::NONE;
		$this->loserName = self::NONE;
		$this->arenaName = $arena;

		$this->maxCountdownTicks = PracticeUtil::secondsToTicks(self::MAX_COUNTDOWN_SEC);

		$this->queue = $group->getQueue();
		$this->ranked = $group->isRanked();

		$player = $group->getPlayer();
		$opponent = $group->getOpponent();

		$p = $player->getPlayer();
		$o = $opponent->getPlayer();

		$this->origPlayerTag = $p->getNameTag();
		$this->origOppTag = $o->getNameTag();

		$this->opponentDevice = $opponent->getDevice();
		$this->playerDevice = $player->getDevice();

		$p->setNameTag(TextFormat::RED . $this->playerName /*. ' ' . $player->getDeviceToStr()*/);
		$o->setNameTag(TextFormat::RED . $this->opponentName/* . ' ' . $opponent->getDeviceToStr()*/);

		$this->started = false;
		$this->ended = false;

		$this->fightingTick = 0;

		$this->currentTick = 0;
		$this->countdownTick = 0;
		$this->endTick = -1;

		$this->playerHits = [];
		$this->oppHits = [];

		$this->arena = PracticeCore::getArenaHandler()->getDuelArena($arena);

		$this->placeInDuel($player, $opponent);

		$this->spectators = [];
		$this->blocks = [];
	}

	/**
	 * @param string $spec
	 * @param bool   $msg
	 *
	 * @return void
	 */
	public function removeSpectator(string $spec, bool $msg = false) : void{
		if($this->isSpectator($spec)){

			$p = PracticeCore::getPlayerHandler()->getPlayer($spec);

			$player = $p->getPlayer();

			PracticeUtil::resetPlayer($player, true);

			$spectators = $this->spectators;

			if($msg === true){
				$msg = PracticeUtil::str_replace(PracticeUtil::getMessage("duels.spectator.leave"), ["%spec%" => "You", "is" => "are"]);
				$p->sendMessage($msg);
				$broadcastedMsg = PracticeUtil::str_replace(PracticeUtil::getMessage("duels.spectator.leave"), ["%spec%" => $player->getName()]);
				$this->broadcastMsg($broadcastedMsg, true, $player);
			}

			unset($spectators[$spec]);
			$this->spectators = $spectators;
		}
	}

	/**
	 * @param string $spec
	 *
	 * @return bool
	 */
	public function isSpectator(string $spec) : bool{
		return isset($this->spectators[$spec]);
	}

	/**
	 * @param string $msg
	 * @param bool   $sendSpecs
	 * @param        $player
	 *
	 * @return void
	 */
	public function broadcastMsg(string $msg, bool $sendSpecs = false, $player = null) : void{
		$oppMsg = $msg;
		$pMsg = $msg;

		if($this->isOpponentOnline()){
			$o = $this->getOpponent();
			if(PracticeUtil::isLineSeparator($oppMsg)){
				if($o->getDevice() === PracticeUtil::WINDOWS_10) $oppMsg .= PracticeUtil::WIN10_ADDED_SEPARATOR;
			}

			$o->sendMessage($oppMsg);
		}

		if($this->isPlayerOnline()){
			$p = $this->getPlayer();
			if(PracticeUtil::isLineSeparator($pMsg)){
				if($p->getDevice() === PracticeUtil::WINDOWS_10) $pMsg .= PracticeUtil::WIN10_ADDED_SEPARATOR;
			}
			$p->sendMessage($pMsg);
		}

		if($sendSpecs === true){

			$spectators = $this->getSpectators();

			$p = (!is_null($player) and PracticeCore::getPlayerHandler()->isPlayerOnline($player)) ? PracticeCore::getPlayerHandler()->getPlayer($player) : null;

			$findPlayer = !is_null($p);

			foreach($spectators as $spec){

				$exec = false;

				if($spec->isOnline()){
					$spectator = $spec->getPlayer();
					if($findPlayer === true and $spectator->equals($p))
						continue;
					else $exec = true;
				}

				if($exec === true){
					$specMsg = $msg;
					$pl = $spec->getPlayer();
					if($pl->getDevice() === PracticeUtil::WINDOWS_10 and PracticeUtil::isLineSeparator($specMsg))
						$specMsg .= PracticeUtil::WIN10_ADDED_SEPARATOR;
					$pl->sendMessage($specMsg);
				}
			}
		}
	}

	/**
	 * @return bool
	 */
	private function isOpponentOnline() : bool{
		return !is_null($this->getOpponent()) and $this->getOpponent()->isOnline();
	}

	/**
	 * @return PracticePlayer|null
	 */
	public function getOpponent() : ?PracticePlayer{
		return PracticeCore::getPlayerHandler()->getPlayer($this->opponentName);
	}

	/**
	 * @return bool
	 */
	private function isPlayerOnline() : bool{
		return !is_null($this->getPlayer()) and $this->getPlayer()->isOnline();
	}

	/**
	 * @return PracticePlayer|null
	 */
	public function getPlayer() : ?PracticePlayer{
		return PracticeCore::getPlayerHandler()->getPlayer($this->playerName);
	}

	/**
	 * @return array
	 */
	private function getSpectators() : array{
		$result = [];

		$keys = array_keys($this->spectators);

		foreach($keys as $key){
			$name = strval($key);
			$spec = $this->spectators[$name];
			if($spec->isOnline())
				$result[] = $spec;
			else unset($this->spectators[$key]);
		}

		return $result;
	}

	/**
	 * @param PracticePlayer $p
	 * @param PracticePlayer $o
	 *
	 * @return void
	 */
	private function placeInDuel(PracticePlayer $p, PracticePlayer $o) : void{
		$p->placeInDuel($this);
		$o->placeInDuel($this);
	}

	/**
	 * @return bool
	 */
	public function isRanked() : bool{
		return $this->ranked;
	}

	/**
	 * @param string $nameTag
	 *
	 * @return void
	 */
	public function setONameTag(string $nameTag) : void{
		$this->origOppTag = $nameTag;
	}

	/**
	 * @param string $nameTag
	 *
	 * @return void
	 */
	public function setPNameTag(string $nameTag) : void{
		$this->origPlayerTag = $nameTag;
	}

	/**
	 * @return string
	 */
	public function getPlayerName() : string{
		return $this->playerName;
	}

	/**
	 * @return string
	 */
	public function getOpponentName() : string{
		return $this->opponentName;
	}

	/**
	 * @return void
	 */
	public function update() : void{

		if(!$this->arePlayersOnline()){
			$this->endDuelPrematurely();
			return;
		}

		$p = $this->getPlayer();
		$o = $this->getOpponent();

		if($this->isLoadingDuel()){

			$this->countdownTick++;

			if($this->countdownTick === 5){
				$p->setDuelScoreboard($this);
				$o->setDuelScoreboard($this);
				ScoreboardUtil::updateSpawnScoreboards();
			}

			if($this->countdownTick % 20 === 0 and $this->countdownTick !== 0){

				$second = self::MAX_COUNTDOWN_SEC - PracticeUtil::ticksToSeconds($this->countdownTick);

				if($second !== 0){
					$msg = PracticeUtil::str_replace(PracticeUtil::getMessage("duels.start.countdown"), ["%seconds%" => "$second"]);
				}else{
					$ranked = ($this->ranked ? "Ranked" : "Unranked");
					$msg = PracticeUtil::str_replace(PracticeUtil::getMessage("duels.start.go-msg"), ["%queue%" => $this->queue, "%ranked%" => $ranked]);
				}

				if(!is_null($msg)) $this->broadcastMsg($msg);
			}

			if($this->countdownTick >= $this->maxCountdownTicks) $this->start();

		}elseif($this->isDuelRunning()){

			$duration = $this->getDuration();
			$maxDuration = PracticeUtil::minutesToTicks(self::MAX_DURATION_MIN);

			if($this->fightingTick > 0){
				$this->fightingTick--;
				if($this->fightingTick <= 0)
					$this->fightingTick = 0;
			}

			if($duration % 20 === 0)
				$this->updateScoreboards();

			if($this->isSumo()){

				if($this->isPlayerBelowCenter($p, 3.0)){
					$this->setResults($this->opponentName, $this->playerName);
					$this->endDuel();
					return;
				}

				if($this->isPlayerBelowCenter($o, 3.0)){
					$this->setResults($this->playerName, $this->opponentName);
					$this->endDuel();
					return;
				}
			}

			if($duration >= $maxDuration){
				$this->setResults();
				$this->endDuel();
				return;
			}

			$size = count($this->spectators);

			if($duration % 20 === 0 and $size > 0){
				$keys = array_keys($this->spectators);
				foreach($keys as $key){
					$spec = $this->spectators[$key];
					$spectator = $spec->getPlayer();
					if(!is_null($spectator) and $spectator->isOnline()){
						$pl = $spectator->getPlayer();
						if($this->isPlayerBelowCenter($spectator, 3.0))
							$pl->teleport($this->arena->getSpawnPosition());
					}else unset($this->spectators[$key]);
				}
			}

		}else{

			$difference = $this->currentTick - $this->endTick;
			$seconds = PracticeUtil::ticksToSeconds($difference);

			if($seconds >= self::MAX_END_DELAY_SEC)
				$this->endDuel();
		}

		$this->currentTick++;
	}

	/**
	 * @return bool
	 */
	public function arePlayersOnline() : bool{
		$result = false;
		if(PracticeCore::getPlayerHandler()->isPlayer($this->opponentName) and PracticeCore::getPlayerHandler()->isPlayer($this->playerName)){
			$opp = $this->getOpponent();
			$pl = $this->getPlayer();
			$result = $opp->isOnline() and $pl->isOnline();
		}
		return $result;
	}

	/**
	 * @param bool $disablePlugin
	 *
	 * @return void
	 */
	public function endDuelPrematurely(bool $disablePlugin = false) : void{

		$winner = self::NONE;

		$loser = self::NONE;

		$premature = true;

		if($disablePlugin === true) $this->setDuelEnded();

		if($disablePlugin === false){

			if($this->isDuelRunning() or $this->didDuelEnd()){

				$results = $this->getOfflinePlayers();

				$winner = $results["winner"];
				$loser = $results["loser"];

				$premature = false;
			}
		}

		$this->winnerName = $winner;
		$this->loserName = $loser;

		$this->endDuel($premature, $disablePlugin);
	}

	/**
	 * @param bool $result
	 *
	 * @return void
	 */
	private function setDuelEnded(bool $result = true){
		$this->ended = $result;
		$this->endTick = $this->endTick == -1 ? $this->currentTick : $this->endTick;
	}

	/**
	 * @return bool
	 */
	public function isDuelRunning() : bool{
		return $this->started === true and $this->ended === false;
	}

	/**
	 * @return bool
	 */
	public function didDuelEnd() : bool{
		return $this->started === true and $this->ended === true;
	}

	/**
	 * @return string[]
	 */
	#[ArrayShape(["winner" => "string", "loser" => "string"])] private function getOfflinePlayers() : array{
		$result = ["winner" => self::NONE, "loser" => self::NONE];

		if(!$this->arePlayersOnline()){

			if(!is_null($this->getPlayer()) and $this->getPlayer()->isOnline()){
				$result["winner"] = $this->playerName;
				$result["loser"] = $this->opponentName;
			}elseif(!is_null($this->getOpponent()) and $this->getOpponent()->isOnline()){
				$result["winner"] = $this->opponentName;
				$result["loser"] = $this->playerName;
			}
		}
		return $result;
	}

	/**
	 * @param bool $endPrematurely
	 * @param bool $disablePlugin
	 *
	 * @return void
	 */
	private function endDuel(bool $endPrematurely = false, bool $disablePlugin = false) : void{
		$this->clearBlocks();

		$messageList = $this->getFinalMessage($endPrematurely);

		$messageList = PracticeUtil::arr_replace_values($messageList, ['*' => PracticeUtil::getLineSeparator($messageList)]);

		$sizeMsgList = count($messageList);

		for($i = 0; $i < $sizeMsgList; $i++){
			$msg = strval($messageList[$i]);
			$this->broadcastMsg($msg, true);
		}

		if($this->isPlayerOnline()){
			$p = $this->getPlayer();
			if($p->getPlayer()->isAlive()){
				PracticeUtil::resetPlayer($p->getPlayer(), true, true, $disablePlugin);
			}
			$p->getPlayer()->setNameTag($this->origPlayerTag);
		}

		if($this->isOpponentOnline()){
			$p = $this->getOpponent();
			if($p->getPlayer()->isAlive()){
				PracticeUtil::resetPlayer($p->getPlayer(), true, true, $disablePlugin);
			}
			$p->getPlayer()->setNameTag($this->origOppTag);
		}

		$keys = array_keys($this->spectators);

		foreach($keys as $key){
			$spectator = $this->spectators[$key];
			$spectator->resetPlayer($disablePlugin);
		}

		PracticeUtil::clearEntitiesIn($this->arena->getWorld(), true, true);

		$this->spectators = [];

		PracticeCore::getArenaHandler()->setArenaOpen($this->arenaName);

		PracticeCore::getDuelHandler()->endDuel($this);
	}

	/**
	 * @return void
	 */
	private function clearBlocks() : void{
		$level = $this->getArena()->getWorld();

		$size = count($this->blocks);

		$spleef = $this->isSpleef();

		for($i = 0; $i < $size; $i++){
			$block = $this->blocks[$i];
			if($block instanceof Position){
				$id = ($spleef === true) ? BlockLegacyIds::SNOW_BLOCK : BlockLegacyIds::AIR;
				$level->setBlockAt($block->x, $block->y, $block->z, $id);
			}
		}

		$this->blocks = [];
	}

	/**
	 * @return DuelArena|null
	 */
	public function getArena() : ?DuelArena{
		return $this->arena;
	}

	/**
	 * @return bool
	 */
	#[Pure] public function isSpleef() : bool{
		return PracticeUtil::equals_string($this->queue, "Spleef", "spleef", "SPLEEF");
	}

	/**
	 * @param bool $endPrematurely
	 *
	 * @return string[]
	 */
	private function getFinalMessage(bool $endPrematurely) : array{
		$resultMsg = $this->getResultMessage();

		$result = ['*', $resultMsg, '*'];

		if($endPrematurely === false){

			if($this->ranked === true and $this->winnerName !== self::NONE and $this->loserName !== self::NONE){

				$winnerDevice = $this->isOpponent($this->winnerName) ? $this->opponentDevice : $this->playerDevice;

				$loserDevice = $this->isOpponent($this->loserName) ? $this->opponentDevice : $this->playerDevice;

				$elo = PracticeCore::getPlayerHandler()->setEloOf($this->winnerName, $this->loserName, $this->queue, $winnerDevice, $loserDevice);

				$winnerChangedElo = $elo['winner-change'];
				$loserChangedElo = $elo['loser-change'];
				$newWElo = $elo['winner'];
				$newLElo = $elo['loser'];

				$eloChanges = $this->getEloChanges($newWElo, $newLElo, $winnerChangedElo, $loserChangedElo);

				array_push($result, $eloChanges, '*');
			}

			$size = count($this->spectators);
			$msg = $this->getSpectatorMessage();
			if(strlen($msg) > 0 and $size > 0 and $msg !== self::NO_SPEC_MSG)
				array_push($result, $msg, '*');
		}

		return $result;
	}

	/**
	 * @return string
	 */
	private function getResultMessage() : string{
		$result = PracticeUtil::str_replace(PracticeUtil::getMessage("duels.end.result-msg"), ["%winner%" => $this->winnerName, "%loser%" => $this->loserName]);
		if($this->winnerName === self::NONE and $this->loserName === self::NONE){
			$result = PracticeUtil::str_replace($result, ["[%wElo%]" => "", "[%lElo%]" => ""]);
		}else{
			$wElo = PracticeCore::getPlayerHandler()->getEloFrom($this->winnerName, $this->queue);
			$lElo = PracticeCore::getPlayerHandler()->getEloFrom($this->loserName, $this->queue);
			$result = PracticeUtil::str_replace($result, ["%wElo%" => "$wElo", "%lElo%" => "$lElo"]);
		}
		return $result;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isOpponent($player) : bool{
		$result = false;
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){
			$p = PracticeCore::getPlayerHandler()->getPlayer($player);
			$name = $p->getPlayerName();
			$result = $name === $this->opponentName;
		}
		return $result;
	}

	/**
	 * @param int $wElo
	 * @param int $lElo
	 * @param int $winner
	 * @param int $loser
	 *
	 * @return string
	 */
	private function getEloChanges(int $wElo, int $lElo, int $winner, int $loser) : string{
		$result = PracticeUtil::getMessage("duels.end.elo-changes");
		$result = PracticeUtil::str_replace($result, ["%winner%" => $this->winnerName, "%loser%" => $this->loserName, "%newWElo%" => "$wElo", "%newLElo%" => $lElo]);
		return PracticeUtil::str_replace($result, ["%wElo%" => "$winner", "%lElo%" => "$loser"]);
	}

	/**
	 * @return string
	 */
	private function getSpectatorMessage() : string{
		$msg = PracticeUtil::getMessage("duels.spectator.end-msg");

		$replaced = "";
		$size = count($this->spectators);

		if($size > 0){

			$len = $size;
			$left = "(+%left% more)";

			if($len > 4){
				$len = 4;
				$others = $size - $len;
				$left = PracticeUtil::str_replace($left, ["%left%" => "$others"]);
			}else $left = null;

			$count = 0;

			$keys = array_keys($this->spectators);

			foreach($keys as $key){
				$name = strval($key);
				if($count < $len){
					$comma = ($count === $len ? '' : ', ');
					$replaced = $replaced . ($name . $comma);
				}else break;
				$count++;
			}

			if(!is_null($left)) $replaced = $replaced . " $left";

			$result = PracticeUtil::str_replace($msg, ["%spec%" => $replaced, "%num%" => "$size"]);

		}else $result = self::NO_SPEC_MSG;

		return $result;
	}

	/**
	 * @return bool
	 */
	public function isLoadingDuel() : bool{
		return $this->started === false and $this->ended === false;
	}

	/**
	 * @return void
	 */
	private function start(){
		$this->started = true;

		if($this->arePlayersOnline()){
			$p = $this->getPlayer();
			$o = $this->getOpponent();

			PracticeUtil::setFrozen($p->getPlayer(), false, true);
			PracticeUtil::setFrozen($o->getPlayer(), false, true);
		}
	}

	/**
	 * @return int
	 */
	#[Pure] public function getDuration() : int{
		$duration = $this->currentTick - $this->countdownTick;
		if($this->didDuelEnd()){
			$endTickDiff = $this->currentTick - $this->endTick;
			$duration = $duration - $endTickDiff;
		}
		return $duration;
	}

	/**
	 * @return void
	 */
	private function updateScoreboards() : void{
		$duration = $this->getDurationString();

		$d = ScoreboardUtil::getNames()['duration'];

		$durationStr = PracticeUtil::str_replace($d, ['%time%' => $duration]);

		if($this->isPlayerOnline()){
			$p = $this->getPlayer();
			$p->updateLineOfScoreboard(2, ' ' . $durationStr);
		}

		if($this->isOpponentOnline()){
			$o = $this->getOpponent();
			$o->updateLineOfScoreboard(2, ' ' . $durationStr);
		}

		$keys = array_keys($this->spectators);

		foreach($keys as $key){
			$spectator = $this->spectators[$key];
			$spectator->update($durationStr);
		}
	}

	/**
	 * @return string
	 */
	public function getDurationString() : string{

		$s = "mm:ss";

		$seconds = PracticeUtil::ticksToSeconds($this->getDuration());
		$minutes = PracticeUtil::ticksToMinutes($this->getDuration());

		if($minutes > 0){
			if($minutes < 10){
				$s = PracticeUtil::str_replace($s, ['mm' => '0' . $minutes]);
			}else{
				$s = PracticeUtil::str_replace($s, ['mm' => $minutes]);
			}
		}else{
			$s = PracticeUtil::str_replace($s, ['mm' => '00']);
		}

		$seconds = $seconds % 60;

		if($seconds > 0){
			if($seconds < 10){
				$s = PracticeUtil::str_replace($s, ['ss' => '0' . $seconds]);
			}else{
				$s = PracticeUtil::str_replace($s, ['ss' => $seconds]);
			}
		}else{
			$s = PracticeUtil::str_replace($s, ['ss' => '00']);
		}

		return $s;
	}

	/**
	 * @return bool
	 */
	#[Pure] private function isSumo() : bool{
		return PracticeUtil::equals_string($this->queue, 'Sumo', 'sumo', 'SUMO', 'sumopvp');
	}

	/**
	 * @param PracticePlayer $player
	 * @param float          $below
	 *
	 * @return bool
	 */
	private function isPlayerBelowCenter(PracticePlayer $player, float $below) : bool{
		$pos = $player->getPlayer()->getPosition();
		$y = $pos->getY();
		$centerY = $this->arena->getSpawnPosition()->y;
		return $y + $below <= $centerY;
	}

	/**
	 * @param string $winner
	 * @param string $loser
	 *
	 * @return void
	 */
	public function setResults(string $winner = self::NONE, string $loser = self::NONE){
		$this->winnerName = $winner;
		$this->loserName = $loser;

		if($winner !== self::NONE and $loser !== self::NONE){

			if($this->arePlayersOnline()){

				$p = $this->getPlayer();
				$playerDuelInfo = new DuelInvInfo($p->getPlayer(), $this->queue, count($this->playerHits));

				$o = $this->getOpponent();
				$oppDuelInfo = new DuelInvInfo($o->getPlayer(), $this->queue, count($this->oppHits));

				$p->addToDuelHistory($playerDuelInfo, $oppDuelInfo);

				$o->addToDuelHistory($oppDuelInfo, $playerDuelInfo);
			}
		}
		$this->setDuelEnded();
	}

	/**
	 * @param $player
	 *
	 * @return void
	 */
	public function addHitFrom($player){
		if($this->isPlayer($player)){

			$hit = new DuelPlayerHit($this->opponentName, $this->currentTick);
			$add = true;

			$size = count($this->oppHits) - 1;

			for($i = $size; $i > -1; $i--){
				$pastHit = $this->oppHits[$i];
				if($pastHit->equals($hit)){
					$add = false;
					break;
				}
			}

			if($add === true) $this->oppHits[] = $hit;

		}elseif($this->isOpponent($player)){

			$hit = new DuelPlayerHit($this->playerName, $this->currentTick);
			$add = true;

			$size = count($this->playerHits) - 1;

			for($i = $size; $i > -1; $i--){
				$pastHit = $this->playerHits[$i];
				if($pastHit->equals($hit)){
					$add = false;
					break;
				}
			}

			if($add === true) $this->playerHits[] = $hit;
		}
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isPlayer($player) : bool{
		$result = false;

		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){
			$p = PracticeCore::getPlayerHandler()->getPlayer($player);
			$name = $p->getPlayerName();
			$result = $name === $this->playerName;
		}

		return $result;
	}

	/**
	 * @return bool
	 */
	public function isInDuelCombat() : bool{
		return $this->fightingTick > 0;
	}

	/**
	 * @return void
	 */
	public function setInDuelCombat() : void{
		$this->fightingTick = PracticeUtil::secondsToTicks(3);
	}

	/**
	 * @return int
	 */
	public function getFightingTick() : int{
		return $this->fightingTick;
	}

	/**
	 * @return string
	 */
	public function getArenaName() : string{
		return $this->arenaName;
	}

	/**
	 * @param Block $against
	 *
	 * @return bool
	 */
	public function canPlaceBlock(Block $against) : bool{
		$count = $this->countPlaced($against);
		return $count < 5;
	}

	/**
	 * @param Block $against
	 *
	 * @return int
	 */
	private function countPlaced(Block $against) : int{
		$count = 0;

		$blAgainst = $against->getPosition();

		if(!$this->isSpleef() and $this->isPlacedBlock($against)){
			$level = $this->arena->getWorld();
			$testPos = $blAgainst->subtract(0, 1, 0);
			$belowBlock = $level->getBlock($testPos);
			$count = $this->countPlaced($belowBlock) + 1;
		}

		return $count;
	}

	/**
	 * @param $block
	 *
	 * @return bool
	 */
	public function isPlacedBlock($block) : bool{
		return $this->indexOfBlock($block) !== -1;
	}

	/**
	 * @param $block
	 *
	 * @return int
	 */
	private function indexOfBlock($block) : int{
		$index = -1;

		if($block instanceof Block){
			$vec = $block->getPosition();
			if(is_null($vec->world)) $vec->world = $this->getArena()->getWorld();
			$index = array_search($vec, $this->blocks);
			if(is_bool($index) and $index === false){
				$index = -1;
			}
		}

		return $index;
	}

	/**
	 * @return bool
	 */
	#[Pure] public function canBreak() : bool{
		$result = $this->canBuild();
		return ($this->isSpleef()) ? true : $result;
	}

	/**
	 * @return bool
	 */
	#[Pure] public function canBuild() : bool{
		return $this->getArena()->canBuild();
	}

	/**
	 * @param Block $position
	 *
	 * @return bool
	 */
	public function removeBlock(Block $position) : bool{
		$result = false;

		if($this->isSpleef() and $this->isSpleefBlock($position)){
			$this->addBlock($position);
			$result = true;
		}else{
			if($this->isPlacedBlock($position)){
				$result = true;
				$index = $this->indexOfBlock($position);
				unset($this->blocks[$index]);
				$this->blocks = array_values($this->blocks);
			}
		}

		return $result;
	}

	/**
	 * @param Block $position
	 *
	 * @return bool
	 */
	private function isSpleefBlock(Block $position) : bool{
		$id = $position->getId();
		return $id === BlockLegacyIds::SNOW_BLOCK or $id === BlockLegacyIds::SNOW_LAYER;
	}

	/**
	 * @param Block $position
	 *
	 * @return void
	 */
	public function addBlock(Block $position) : void{
		$pos = $position->getPosition();
		$this->blocks[] = $pos;
	}

	/**
	 * @param $spectator
	 *
	 * @return void
	 */
	public function addSpectator($spectator) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($spectator)){
			$p = PracticeCore::getPlayerHandler()->getPlayer($spectator);

			$pl = $p->getPlayer();

			$name = $pl->getName();

			$spec = new DuelSpectator($pl);

			$center = $this->getArena()->getSpawnPosition();

			$spec->teleport($center);

			$this->spectators[$name] = $spec;

			$msg = PracticeUtil::getMessage("duels.spectator.join");
			$msg = PracticeUtil::str_replace($msg, ["%spec%" => $name]);
			$this->broadcastMsg($msg, true);

			$p->setSpectatorScoreboard($this);
		}
	}

	/**
	 * @param $object
	 *
	 * @return bool
	 */
	public function equals($object) : bool{
		$result = false;
		if($object instanceof DuelGroup){
			if($object->getPlayer() === $this->getPlayer() and $object->getOpponent() === $this->getOpponent())
				$result = $object->getQueue() === $this->getQueue();
		}
		return $result;
	}

	/**
	 * @return string
	 */
	public function getQueue() : string{
		return $this->queue;
	}

	/**
	 * @return string
	 */
	public function queueToString() : string{
		$str = PracticeUtil::getName('scoreboard.duels.kit');

		return PracticeUtil::str_replace($str, ['%kit%' => $this->queue]);
	}
}