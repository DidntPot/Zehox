<?php

/**
 * @noinspection PhpPropertyOnlyWrittenInspection
 */

declare(strict_types=1);

namespace practice\player;

use JetBrains\PhpStorm\Pure;
use pocketmine\entity\Location;
use pocketmine\form\Form;
use pocketmine\item\Item;
use pocketmine\player\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\sound\ThrowSound;
use practice\arenas\FFAArena;
use practice\arenas\PracticeArena;
use practice\duels\groups\DuelGroup;
use practice\duels\misc\DuelInvInfo;
use practice\forms\SimpleForm;
use practice\game\entity\FishingHook;
use practice\game\FormUtil;
use practice\PracticeCore;
use practice\PracticeUtil;
use practice\scoreboard\Scoreboard;
use practice\scoreboard\ScoreboardUtil;

class PracticePlayer{
	/** @var int */
	public const MAX_COMBAT_TICKS = 10;
	/** @var int */
	public const MAX_ENDERPEARL_SECONDS = 15;

	/** @var bool */
	private bool $inCombat;
	/** @var bool */
	private bool $canThrowPearl;
	/** @var bool */
	private bool $hasKit;
	/** @var bool */
	private bool $antiSpam;
	/** @var bool */
	private bool $canHitPlayer;
	/** @var bool */
	private bool $isLookingAtForm;
	/** @var mixed */
	private mixed $invId;

	/** @var string */
	private string $playerName;
	/** @var string */
	private string $currentName;
	/** @var string */
	private string $currentArena;
	/** @var string */
	private string $deviceId;
	/** @var string */
	private string $deviceModel;

	/** @var int */
	private int $currentSec;
	/** @var int */
	private int $antiSendSecs;
	/** @var int */
	private int $lastSecHit;
	/** @var int */
	private int $combatSecs;
	/** @var int */
	private int $enderpearlSecs;
	/** @var int */
	private int $antiSpamSecs;
	/** @var int */
	private int $deviceOs;
	/** @var int */
	private int $input;
	/** @var int */
	private int $duelSpamSec;
	/** @var int */
	private int $noDamageTick;
	/** @var int|float */
	private int|float $lastMicroTimeHit;
	/** @var int */
	private int $cid;

	/** @var array */
	private array $currentFormData;
	/** @var array */
	private array $cps = [];

	/** @var mixed|null */
	private mixed $fishing;
	/** @var array */
	private array $duelResultInvs;

	/* @var Scoreboard */
	private Scoreboard $scoreboard;
	/** @var mixed */
	private mixed $scoreboardType;
	/** @var array */
	private array $scoreboardNames;

	/** @var array */
	private array $enderpearlThrows;

	/**
	 * PracticePlayer constructor.
	 *
	 * @param Player $player
	 * @param int    $deviceOs
	 * @param int    $input
	 * @param string $deviceID
	 * @param int    $clientID
	 * @param string $deviceModel
	 */
	public function __construct(Player $player, int $deviceOs, int $input, string $deviceID, int $clientID, string $deviceModel){
		$this->deviceOs = $deviceOs;
		$this->input = $input;
		$this->deviceId = $deviceID;
		$this->cid = $clientID;
		$this->deviceModel = $deviceModel;
		$this->playerName = $player->getName();
		$this->currentName = $this->playerName;

		$this->inCombat = false;
		$this->canThrowPearl = true;
		$this->hasKit = false;
		$this->antiSpam = false;
		$this->canHitPlayer = false;
		$this->isLookingAtForm = false;

		$this->currentArena = PracticeArena::NO_ARENA;

		$this->currentSec = 0;
		$this->antiSendSecs = 0;
		$this->lastSecHit = 0;
		$this->combatSecs = 0;
		$this->enderpearlSecs = 0;
		$this->antiSpamSecs = 0;
		$this->duelSpamSec = 0;
		$this->noDamageTick = 0;
		$this->invId = -1;
		$this->lastMicroTimeHit = 0;

		$this->scoreboardNames = ScoreboardUtil::getNames();

		$this->currentFormData = [];

		$this->fishing = null;
		$this->duelResultInvs = [];
		$this->enderpearlThrows = [];

		$this->initScoreboard(!PracticeCore::getPlayerHandler()->isScoreboardEnabled($this->playerName));
	}

	/**
	 * @param bool $hide
	 *
	 * @return void
	 */
	private function initScoreboard(bool $hide = false) : void{
		$name = PracticeUtil::getName('server-name');
		$this->scoreboardType = 'scoreboard.spawn';
		$this->scoreboard = new Scoreboard($this, $name);
		if($hide === true) $this->hideScoreboard();
		else $this->setSpawnScoreboard(false, false);
	}

	/**
	 * @return void
	 */
	public function hideScoreboard() : void{
		$this->scoreboard->removeScoreboard();
	}

	/**
	 * @param bool $queue
	 * @param bool $clear
	 *
	 * @return void
	 */
	public function setSpawnScoreboard(bool $queue = false, bool $clear = true) : void{
		if($clear === true) $this->scoreboard->clearScoreboard();

		$server = Server::getInstance();

		$onlinePlayers = count($server->getOnlinePlayers());

		$inFights = PracticeCore::getPlayerHandler()->getPlayersInFights();

		$inQueues = PracticeCore::getDuelHandler()->getNumberOfQueuedPlayers();

		$onlineStr = PracticeUtil::str_replace($this->scoreboardNames['online'], ['%num%' => $onlinePlayers, '%max-num%' => $server->getMaxPlayers()]);
		$inFightsStr = PracticeUtil::str_replace($this->scoreboardNames['in-fights'], ['%num%' => $inFights]);
		$inQueuesStr = PracticeUtil::str_replace($this->scoreboardNames['in-queues'], ['%num%' => $inQueues]);

		$arr = [$onlineStr, $inFightsStr, $inQueuesStr];

		if($queue === true){

			$duelHandler = PracticeCore::getDuelHandler();

			if($duelHandler->isPlayerInQueue($this->playerName)){

				$queuePlayer = $duelHandler->getQueuedPlayer($this->playerName);

				$str = ' ' . $queuePlayer->toString();

				$this->scoreboard->addLine(5, $str);

				$arr[] = $str . '   ';
			}
		}

		$compare = PracticeUtil::getLineSeparator($arr);

		$separator = '-------------';

		$len = strlen($separator);

		$len1 = strlen($compare);

		$compare = substr($compare, 0, $len1 - 1);

		$len1--;

		if($len1 > $len) $separator = $compare;

		if($this->deviceOs === PracticeUtil::WINDOWS_10) $separator .= PracticeUtil::WIN10_ADDED_SEPARATOR;

		$this->scoreboard->addLine(0, ' ' . TextFormat::RED . TextFormat::WHITE . $separator);

		$this->scoreboard->addLine(1, ' ' . $onlineStr);

		$this->scoreboard->addLine(2, ' ' . $inFightsStr);

		$this->scoreboard->addLine(3, ' ' . $inQueuesStr);

		$this->scoreboard->addLine(4, ' ' . TextFormat::GOLD . TextFormat::WHITE . $separator);

		if($queue === true)

			$this->scoreboard->addLine(6, ' ' . TextFormat::GREEN . TextFormat::WHITE . $separator);

		$this->scoreboardType = 'scoreboard.spawn';
	}

	/**
	 * @return void
	 */
	public function showScoreboard() : void{
		$this->scoreboard->resendScoreboard();
		$this->setSpawnScoreboard(false, false);
	}

	/**
	 * @return string
	 */
	public function getScoreboard() : string{
		return $this->scoreboardType;
	}

	/**
	 * @param DuelGroup $group
	 *
	 * @return void
	 */
	public function setDuelScoreboard(DuelGroup $group) : void{

		$this->scoreboard->clearScoreboard();

		$opponent = ($group->isPlayer($this->playerName)) ? $group->getOpponent() : $group->getPlayer();

		$name = $opponent->getPlayerName();

		$opponentStr = PracticeUtil::str_replace($this->scoreboardNames['opponent'], ['%player%' => $name]);
		$durationStr = PracticeUtil::str_replace($this->scoreboardNames['duration'], ['%time%' => '00:00']);

		$theirCPS = PracticeUtil::str_replace($this->scoreboardNames['opponent-cps'], ['%player%' => 'Their', '%clicks%' => 0]);
		$yourCPS = PracticeUtil::str_replace($this->scoreboardNames['player-cps'], ['%player%' => 'Your', '%clicks%' => 0]);

		$arr = [$opponentStr, $durationStr, $theirCPS, $yourCPS];

		$compare = PracticeUtil::getLineSeparator($arr);

		$separator = '-------------';

		$len = strlen($separator);

		$len1 = strlen($compare);

		$compare = substr($compare, 0, $len1 - 1);

		$len1--;

		if($len1 > $len) $separator = $compare;

		if($this->deviceOs === PracticeUtil::WINDOWS_10) $separator .= PracticeUtil::WIN10_ADDED_SEPARATOR;

		$this->scoreboard->addLine(0, ' ' . TextFormat::RED . TextFormat::WHITE . $separator);

		$this->scoreboard->addLine(1, ' ' . $opponentStr);

		$this->scoreboard->addLine(2, ' ' . $durationStr);

		$this->scoreboard->addLine(3, ' ' . TextFormat::GREEN . TextFormat::WHITE . $separator);

		$this->scoreboard->addLine(4, ' ' . $yourCPS);

		$this->scoreboard->addLine(5, ' ' . $theirCPS);

		$this->scoreboard->addLine(6, ' ' . TextFormat::BLUE . TextFormat::WHITE . $separator);

		$this->scoreboardType = 'scoreboard.duel';
	}

	/**
	 * @return string
	 */
	public function getPlayerName() : string{
		return $this->playerName;
	}

	/**
	 * @param DuelGroup $group
	 *
	 * @return void
	 */
	public function setSpectatorScoreboard(DuelGroup $group) : void{

		$this->scoreboard->clearScoreboard();

		$duration = $group->getDurationString();

		$queue = $group->queueToString();

		$durationStr = PracticeUtil::str_replace($this->scoreboardNames['duration'], ['%time%' => $duration]);

		$arr = [$durationStr, $queue];

		$compare = PracticeUtil::getLineSeparator($arr);

		$separator = '-------------';

		$len = strlen($separator);

		$len1 = strlen($compare);

		$compare = substr($compare, 0, $len1 - 1);

		$len1--;

		if($len1 > $len) $separator = $compare;

		if($this->deviceOs === PracticeUtil::WINDOWS_10) $separator .= PracticeUtil::WIN10_ADDED_SEPARATOR;

		$this->scoreboard->addLine(0, ' ' . TextFormat::RED . TextFormat::WHITE . $separator);

		$this->scoreboard->addLine(1, ' ' . $queue);

		$this->scoreboard->addLine(2, ' ' . $durationStr);

		$this->scoreboard->addLine(3, ' ' . TextFormat::BLACK . TextFormat::WHITE . $separator);

		$this->scoreboardType = 'scoreboard.spec';
	}

	/**
	 * @param int $del
	 *
	 * @return void
	 */
	public function setNoDamageTicks(int $del) : void{
		$this->noDamageTick = $del;
	}

	/**
	 * @return int
	 */
	public function getNoDamageTicks() : int{
		return $this->noDamageTick;
	}

	/**
	 * @return void
	 */
	public function updatePlayer() : void{
		$this->currentSec++;

		$this->updateCps();

		if($this->isOnline() and !$this->isInArena()){

			$p = $this->getPlayer();
			$level = $p->getWorld();

			if($this->currentSec % 5 === 0){

				$resetHunger = PracticeUtil::areWorldEqual($level, PracticeUtil::getDefaultWorld());

				if($resetHunger === false and $this->isInDuel()){
					$duel = PracticeCore::getDuelHandler()->getDuel($this->playerName);
					$resetHunger = PracticeUtil::equals_string($duel->getQueue(), 'Sumo', 'SumoPvP', 'sumo');
				}

				if($resetHunger === true){
					$p->getHungerManager()->setFood($p->getHungerManager()->getMaxFood());
				}
			}
		}

		if(PracticeUtil::isEnderpearlCooldownEnabled()){
			if(!$this->canThrowPearl()){
				$this->removeSecInThrow();
				if($this->enderpearlSecs <= 0)
					$this->setThrowPearl(true);
			}
		}

		if($this->isInAntiSpam()){
			$this->antiSpamSecs--;
			if($this->antiSpamSecs <= 0) $this->setInAntiSpam(false);
		}

		if($this->isInCombat()){
			$this->combatSecs--;
			if($this->combatSecs <= 0){
				$this->setInCombat(false);
			}
		}

		if($this->canSendDuelRequest() !== true) $this->duelSpamSec--;
	}

	/**
	 * @return void
	 */
	private function updateCps() : void{

		$cps = $this->cps;

		$microtime = microtime(true);

		$keys = array_keys($cps);

		foreach($keys as $key){
			$thecps = floatval($key);
			if($microtime - $thecps > 1)
				unset($cps[$key]);
		}

		$this->cps = $cps;

		$yourCPS = count($this->cps);

		$yourCPSStr = PracticeUtil::str_replace($this->scoreboardNames['player-cps'], ['%player%' => 'Your', '%clicks%' => $yourCPS]);

		if($this->scoreboardType === 'scoreboard.duel' and $this->isInDuel()){

			$duel = PracticeCore::getDuelHandler()->getDuel($this->playerName);

			if($duel->isDuelRunning() and $duel->arePlayersOnline()){

				$theirCPSStr = PracticeUtil::str_replace($this->scoreboardNames['opponent-cps'], ['%player%' => 'Their', '%clicks%' => $yourCPS]);

				$other = $duel->isPlayer($this->playerName) ? $duel->getOpponent() : $duel->getPlayer();

				$this->updateLineOfScoreboard(4, ' ' . $yourCPSStr);

				$other->updateLineOfScoreboard(5, ' ' . $theirCPSStr);
			}
		}elseif($this->scoreboardType === 'scoreboard.ffa'){

			$this->updateLineOfScoreboard(3, ' ' . $yourCPSStr);
		}
	}

	/**
	 * @return bool
	 */
	public function isInDuel() : bool{
		return PracticeCore::getDuelHandler()->isInDuel($this->playerName);
	}

	/**
	 * @param int    $id
	 * @param string $line
	 *
	 * @return void
	 */
	public function updateLineOfScoreboard(int $id, string $line) : void{

		$this->scoreboard->addLine($id, $line);

	}

	/**
	 * @return bool
	 */
	public function isOnline() : bool{
		return isset($this->playerName) and !is_null($this->getPlayer()) and $this->getPlayer()->isOnline();
	}

	/**
	 * @return Player|null
	 */
	public function getPlayer() : ?Player{
		return Server::getInstance()->getPlayerExact($this->playerName);
	}

	/**
	 * @return bool
	 */
	public function isInArena() : bool{
		return $this->currentArena !== PracticeArena::NO_ARENA;
	}

	/**
	 * @return bool
	 */
	public function canThrowPearl() : bool{
		return $this->canThrowPearl;
	}

	/**
	 * @return void
	 */
	private function removeSecInThrow() : void{
		$this->enderpearlSecs--;
		$maxSecs = self::MAX_ENDERPEARL_SECONDS;
		$sec = $this->enderpearlSecs;
		if($sec < 0) $sec = 0;
		$percent = floatval($this->enderpearlSecs / $maxSecs);
		if($this->isOnline()){
			$p = $this->getPlayer();
			$p->getXpManager()->setXpLevel($sec);
			$p->getXpManager()->setXpProgress($percent);
		}
	}

	/**
	 * @param bool $res
	 *
	 * @return void
	 */
	public function setThrowPearl(bool $res) : void{
		if($res === false){
			$this->enderpearlSecs = self::MAX_ENDERPEARL_SECONDS;
			if($this->isOnline()){
				$p = $this->getPlayer();
				if($this->canThrowPearl === true)
					$p->sendMessage(PracticeUtil::getMessage('general.enderpearl-cooldown.cooldown-place'));

				$p->getXpManager()->setXpProgress(1.0);
				$p->getXpManager()->setXpLevel(self::MAX_ENDERPEARL_SECONDS);
			}
		}else{
			$this->enderpearlSecs = 0;
			if($this->isOnline()){
				$p = $this->getPlayer();
				if($this->canThrowPearl === false)
					$p->sendMessage(PracticeUtil::getMessage('general.enderpearl-cooldown.cooldown-remove'));

				$p->getXpManager()->setXpLevel(0);
				$p->getXpManager()->setXpProgress(0);
			}
		}
		$this->canThrowPearl = $res;
	}

	/**
	 * @return bool
	 */
	public function isInAntiSpam() : bool{
		return $this->antiSpam;
	}

	/**
	 * @param bool $res
	 *
	 * @return void
	 */
	public function setInAntiSpam(bool $res) : void{
		$this->antiSpam = $res;
		if($this->antiSpam === true)
			$this->antiSpamSecs = 5;
		else $this->antiSpamSecs = 0;
	}

	/**
	 * @return bool
	 */
	public function isInCombat() : bool{
		return $this->inCombat;
	}

	/**
	 * @param bool $res
	 *
	 * @return void
	 */
	public function setInCombat(bool $res) : void{

		if($res === true){
			$this->lastSecHit = $this->currentSec;
			$this->combatSecs = self::MAX_COMBAT_TICKS;
			if($this->isOnline()){
				$p = $this->getPlayer();
				if($this->inCombat === false)
					$p->sendMessage(PracticeUtil::getMessage('general.combat.combat-place'));
			}
		}else{
			$this->combatSecs = 0;
			if($this->isOnline()){
				$p = $this->getPlayer();
				if($this->inCombat === true)
					$p->sendMessage(PracticeUtil::getMessage('general.combat.combat-remove'));

			}
		}
		$this->inCombat = $res;
	}

	/**
	 * @return bool
	 */
	public function canSendDuelRequest() : bool{
		return $this->duelSpamSec <= 0;
	}

	/**
	 * @return void
	 */
	public function updateNoDmgTicks() : void{
		if($this->noDamageTick > 0){
			$this->noDamageTick--;
			if($this->noDamageTick <= 0)
				$this->noDamageTick = 0;
		}
	}

	/**
	 * @return void
	 */
	public function setCantSpamDuel() : void{
		//$this->duelSpamTick = PracticeUtil::ticksToSeconds(20);
		$this->duelSpamSec = 20;
	}

	/**
	 * @return int
	 */
	public function getCantDuelSpamSecs() : int{
		return $this->duelSpamSec;
	}

	/**
	 * @param DuelInvInfo $player
	 * @param DuelInvInfo $opponent
	 *
	 * @return void
	 */
	public function addToDuelHistory(DuelInvInfo $player, DuelInvInfo $opponent) : void{
		$this->duelResultInvs[] = ['player' => $player, 'opponent' => $opponent];
	}

	/**
	 * @param Item $item
	 *
	 * @return bool
	 */
	public function isDuelHistoryItem(Item $item) : bool{

		$result = false;

		if($this->hasInfoOfLastDuel()){

			$pInfo = $this->getInfoOfLastDuel()['player'];
			$oInfo = $this->getInfoOfLastDuel()['opponent'];

			if($pInfo instanceof DuelInvInfo and $oInfo instanceof DuelInvInfo)
				$result = ($pInfo->getItem()->equalsExact($item) or $oInfo->getItem()->equalsExact($item));
		}
		return $result;
	}

	/**
	 * @return bool
	 */
	public function hasInfoOfLastDuel() : bool{
		return $this->hasDuelInvs() and count($this->getInfoOfLastDuel()) > 0;
	}

	/**
	 * @return bool
	 */
	public function hasDuelInvs() : bool{
		return count($this->duelResultInvs) > 0;
	}

	/**
	 * @return array
	 */
	public function getInfoOfLastDuel() : array{

		$count = count($this->duelResultInvs);

		return ($count > 0) ? $this->duelResultInvs[$count - 1] : [];
	}

	/**
	 * @return void
	 */
	public function spawnResInvItems() : void{

		if($this->isOnline()){

			$inv = $this->getPlayer()->getInventory();

			if($this->hasInfoOfLastDuel()){

				$res = $this->getInfoOfLastDuel();

				$p = $res['player'];
				$o = $res['opponent'];

				if($p instanceof DuelInvInfo and $o instanceof DuelInvInfo){

					$inv->clearAll();

					$exitItem = PracticeCore::getItemHandler()->getExitInventoryItem();

					$slot = $exitItem->getSlot();

					$item = $exitItem->getItem();

					$inv->setItem(0, $p->getItem());

					$inv->setItem(1, $o->getItem());

					$inv->setItem($slot, $item);
				}

			}else $this->sendMessage(PracticeUtil::getMessage('view-res-inv-msg'));

		}
	}

	/**
	 * @param string $msg
	 *
	 * @return void
	 */
	public function sendMessage(string $msg) : void{
		if($this->isOnline()){
			$p = $this->getPlayer();
			$p->sendMessage($msg);
		}
	}

	/**
	 * @return void
	 */
	public function startFishing() : void{
		if($this->isOnline()){
			$player = $this->getPlayer();

			if(!$player instanceof Player) return;

			if($player !== null and !$this->isFishing()){
				$location = $player->getLocation();
				$hook = new FishingHook(Location::fromObject($player->getEyePos()->add(0, 0.5, 0), $location->getWorld(), $location->getYaw(), $location->getPitch()), $this->getPlayer());

				$hook->spawnToAll();
				$location->getWorld()->addSound($location, new ThrowSound());
				$this->fishing = true;
			}
		}
	}

	/**
	 * @return bool
	 */
	public function isFishing() : bool{
		return $this->fishing !== null;
	}

	/**
	 * @param bool $click
	 * @param bool $killEntity
	 *
	 * @return void
	 */
	public function stopFishing(bool $click = true, bool $killEntity = true) : void{
		if($this->isFishing()){
			if($this->fishing instanceof FishingHook){
				$rod = $this->fishing;
				if($click === true){
					$rod->reelLine();
				}elseif($rod !== null){
					if(!$rod->isClosed() and $killEntity === true){
						$rod->kill();
						$rod->close();
					}
				}
			}
		}

		$this->fishing = null;
	}

	/**
	 * @return int
	 */
	public function getCurrentSec() : int{
		return $this->currentSec;
	}

	/**
	 * @return bool
	 */
	public function isInvisible() : bool{
		return $this->getPlayer()->isInvisible();
	}

	/**
	 * @param bool $res
	 *
	 * @return void
	 */
	public function setInvisible(bool $res) : void{
		if($this->isOnline()) $this->getPlayer()->setInvisible($res);
	}

	/**
	 * @param bool $res
	 *
	 * @return void
	 */
	public function setHasKit(bool $res) : void{
		$this->hasKit = $res;
	}

	/**
	 * @return bool
	 */
	public function doesHaveKit() : bool{
		return $this->hasKit;
	}

	/**
	 * @return int
	 */
	public function getLastSecInCombat() : int{
		return $this->lastSecHit;
	}

	/**
	 * @return void
	 */
	public function trackHit() : void{
		$this->lastMicroTimeHit = microtime(true);
	}

	/**
	 * @return string
	 */
	public function getCurrentArenaType() : string{
		$type = PracticeArena::NO_ARENA;

		$arena = $this->getCurrentArena();

		if($this->isInArena() and !is_null($arena))
			$type = $arena->getArenaType();

		return $type;
	}

	/**
	 * @return mixed
	 */
	public function getCurrentArena() : mixed{
		return PracticeCore::getArenaHandler()->getArena($this->currentArena);
	}

	/**
	 * @param string $currentArena
	 *
	 * @return void
	 */
	public function setCurrentArena(string $currentArena) : void{
		$this->currentArena = $currentArena;
	}

	/**
	 * @param FFAArena $arena
	 *
	 * @return void
	 */
	public function teleportToFFA(FFAArena $arena){
		if($this->isOnline()){
			$player = $this->getPlayer();
			$spawn = $arena->getSpawnPosition();

			$duelHandler = PracticeCore::getDuelHandler();

			if($duelHandler->isPlayerInQueue($player))
				$duelHandler->removePlayerFromQueue($player, true);

			if(!is_null($spawn)){

				PracticeUtil::onChunkGenerated($spawn->world, intval($spawn->x) >> 4, intval($spawn->z) >> 4, function() use ($player, $spawn){
					$player->teleport($spawn);
				});

				$arenaName = $arena->getName();
				$this->currentArena = $arenaName;

				if($arena->doesHaveKit()){
					$kit = $arena->getFirstKit();
					$kit->giveTo($this, true);
				}

				$this->setCanHitPlayer(true);
				$msg = PracticeUtil::getMessage('general.arena.join');
				$msg = strval(str_replace('%arena-name%', $arenaName, $msg));

				$this->setFFAScoreboard($arena);

			}else{

				$msg = PracticeUtil::getMessage('general.arena.fail');
				$msg = strval(str_replace('%arena-name%', $arena->getName(), $msg));
			}

			if(!is_null($msg)) $player->sendMessage($msg);
		}
	}

	/**
	 * @param bool $res
	 *
	 * @return void
	 */
	public function setCanHitPlayer(bool $res) : void{
		$p = $this->getPlayer();
		if($this->isOnline()) PracticeUtil::setCanHit($p, $res);
		$this->canHitPlayer = $res;
	}

	/**
	 * @param FFAArena $arena
	 *
	 * @return void
	 */
	public function setFFAScoreboard(FFAArena $arena) : void{

		$this->scoreboard->clearScoreboard();

		$playerHandler = PracticeCore::getPlayerHandler();

		$arenaName = $arena->getName();

		$kills = $playerHandler->getKillsOf($this->playerName);

		$deaths = $playerHandler->getDeathsOf($this->playerName);

		if(PracticeUtil::str_contains('FFA', $this->scoreboardNames['arena']) and PracticeUtil::str_contains('FFA', $arenaName))
			$arenaName = PracticeUtil::str_replace($arenaName, ['FFA' => '']);

		$killsStr = PracticeUtil::str_replace($this->scoreboardNames['kills'], ['%num%' => $kills]);
		$deathsStr = PracticeUtil::str_replace($this->scoreboardNames['deaths'], ['%num%' => $deaths]);
		$yourCPS = PracticeUtil::str_replace($this->scoreboardNames['player-cps'], ['%player%' => 'Your', '%clicks%' => 0]);
		$arenaStr = trim(PracticeUtil::str_replace($this->scoreboardNames['arena'], ['%arena%' => $arenaName]));

		$arr = [$killsStr, $deathsStr, $arenaStr, $yourCPS];

		$compare = PracticeUtil::getLineSeparator($arr);

		$separator = '-------------';

		$len = strlen($separator);

		$len1 = strlen($compare);

		$compare = substr($compare, 0, $len1 - 1);

		$len1--;

		if($len1 > $len) $separator = $compare;

		if($this->deviceOs === PracticeUtil::WINDOWS_10) $separator .= PracticeUtil::WIN10_ADDED_SEPARATOR;

		$this->scoreboard->addLine(0, ' ' . TextFormat::RED . TextFormat::WHITE . $separator);

		$this->scoreboard->addLine(1, ' ' . $arenaStr);

		$this->scoreboard->addLine(2, ' ' . TextFormat::GREEN . TextFormat::WHITE . $separator);

		$this->scoreboard->addLine(3, ' ' . $yourCPS);

		$this->scoreboard->addLine(4, ' ' . $killsStr);

		$this->scoreboard->addLine(5, ' ' . $deathsStr);

		$this->scoreboard->addLine(6, ' ' . TextFormat::GOLD . TextFormat::WHITE . $separator);

		$this->scoreboardType = 'scoreboard.ffa';
	}

	/**
	 * @return bool
	 */
	public function canHitPlayer() : bool{
		return $this->canHitPlayer;
	}

	/**
	 * @return void
	 */
	public function trackThrow() : void{

		$time = microtime(true);

		$key = "$time";

		$this->enderpearlThrows[$key] = false;
	}

	/**
	 * @return void
	 */
	public function checkSwitching() : void{

		$time = microtime(true);

		$count = count($this->enderpearlThrows);

		$keys = array_keys($this->enderpearlThrows);

		if($count > 0 and $this->isOnline()){

			$len = $count - 1;

			$lastThrow = floatval($keys[$len]);

			$differenceHitNThrow = abs($time - $lastThrow) + 10;
			$differenceHitNThisHit = abs($time - $this->lastMicroTimeHit);

			$ticks = 0.05 * 11.25;

			$result = $this->lastMicroTimeHit !== 0 and $differenceHitNThrow < $differenceHitNThisHit and $differenceHitNThisHit <= $ticks;

			/*$print = ($result === true) ? "true" : "false";

			$str = "$print : $differenceHitNThisHit : $differenceHitNThrow \n";
			var_dump($str);*/

			if($result === true) $this->enderpearlThrows["$lastThrow"] = true;
		}
	}

	/**
	 * @return bool
	 */
	public function isSwitching() : bool{

		$keys = array_keys($this->enderpearlThrows);

		$count = count($this->enderpearlThrows);

		$result = false;

		$time = microtime(true);

		//$difference = 0;

		if($count > 0){

			$len = $count - 1;

			$key = $keys[$len];

			$lastMicroTime = floatval($key);

			$result = boolval($this->enderpearlThrows[$key]);

			if($result === true){

				$ticks = 0.05 * 12.5;

				$difference = abs($time - $lastMicroTime);

				$result = $difference <= $ticks;
			}
		}

		/*$print = ($result === true ? "true" : "false") . " : $difference";

		var_dump($print);*/

		return $result;
	}

	/**
	 * @param bool $clickedBlock
	 *
	 * @return void
	 */
	public function addCps(bool $clickedBlock) : void{

		$microtime = microtime(true);

		$keys = array_keys($this->cps);

		$size = count($keys);

		foreach($keys as $key){
			$cps = floatval($key);
			if($microtime - $cps > 1)
				unset($this->cps[$key]);
		}

		if($clickedBlock === true and $size > 0){
			$index = $size - 1;
			$lastKey = $keys[$index];
			$cps = floatval($lastKey);
			if(isset($this->cps[$lastKey])){
				$val = $this->cps[$lastKey];
				$diff = $microtime - $cps;
				if($val === true and $diff <= 0.05)
					unset($this->cps[$lastKey]);
			}
		}

		$this->cps["$microtime"] = $clickedBlock;

		$yourCPS = count($this->cps);

		$yourCPSStr = PracticeUtil::str_replace($this->scoreboardNames['player-cps'], ['%player%' => 'Your', '%clicks%' => $yourCPS]);
		$this->getPlayer()?->sendTip($yourCPSStr);

		if($this->scoreboardType === 'scoreboard.duel' and $this->isInDuel()){

			$duel = PracticeCore::getDuelHandler()->getDuel($this->playerName);

			if($duel->isDuelRunning() and $duel->arePlayersOnline()){

				$theirCPSStr = PracticeUtil::str_replace($this->scoreboardNames['opponent-cps'], ['%player%' => 'Their', '%clicks%' => $yourCPS]);

				$other = $duel->isPlayer($this->playerName) ? $duel->getOpponent() : $duel->getPlayer();

				$this->updateLineOfScoreboard(4, ' ' . $yourCPSStr);

				$other->updateLineOfScoreboard(5, ' ' . $theirCPSStr);
			}
		}elseif($this->scoreboardType === 'scoreboard.ffa'){

			$this->updateLineOfScoreboard(3, ' ' . $yourCPSStr);
		}
	}

	/**
	 * @return int
	 */
	public function getInput() : int{
		return $this->input;
	}

	/**
	 * @return int
	 */
	public function getDevice() : int{
		return $this->deviceOs;
	}

	/**
	 * @return string
	 */
	public function getDeviceID() : string{
		return $this->deviceId;
	}

	/**
	 * @return int
	 */
	public function getCID() : int{
		return $this->cid;
	}

	/*public function setInput(int $val) : void {
		if($this->input === -1)
			$this->input = $val;
	}

	public function setDeviceOS(int $val) : void {
		if($this->deviceOs === PracticeUtil::UNKNOWN)
			$this->deviceOs = $val;
	}

	public function setCID(int $cid) : void {
		$this->cid = $cid;
	}

	public function setDeviceID(string $id) : void {
		$this->deviceId = $id;
	}*/

	/**
	 * @return bool
	 */
	public function peOnlyQueue() : bool{
		return $this->deviceOs !== PracticeUtil::WINDOWS_10 and $this->input === PracticeUtil::CONTROLS_TOUCH;
	}

	/**
	 * @return bool
	 */
	public function isInParty() : bool{
		return PracticeCore::getPartyManager()->isPlayerInParty($this->playerName);
	}

	/**
	 * @param bool $sendMsg
	 *
	 * @return bool
	 */
	public function canUseCommands(bool $sendMsg = true) : bool{
		$result = false;
		if($this->isOnline()){
			$msg = null;
			if($this->isInDuel()){
				$msgStr = ($this->isInCombat()) ? 'general.combat.command-msg' : 'general.duels.command-msg';
				$msg = PracticeUtil::getMessage($msgStr);
			}else{
				if($this->isInCombat())
					$msg = PracticeUtil::getMessage('general.combat.command-msg');
				else $result = true;
			}
			if(!is_null($msg) and $sendMsg) $this->getPlayer()->sendMessage($msg);
		}
		return $result;
	}

	/**
	 * @param DuelGroup $grp
	 *
	 * @return void
	 */
	public function placeInDuel(DuelGroup $grp) : void{

		if($this->isOnline()){

			$p = $this->getPlayer();

			$arena = $grp->getArena();

			$isPlayer = $grp->isPlayer($this->playerName);

			$pos = ($isPlayer === true) ? $arena->getPlayerPos() : $arena->getOpponentPos();

			$oppName = ($isPlayer === true) ? $grp->getOpponent()->getPlayerName() : $grp->getPlayer()->getPlayerName();

			$p->setGamemode(GameMode::SURVIVAL());

			PracticeUtil::onChunkGenerated($pos->world, intval($pos->x) >> 4, intval($pos->z) >> 4, function() use ($p, $pos){
				$p->teleport($pos);
			});

			$queue = $grp->getQueue();

			if($arena->hasKit($queue)){
				$kit = $arena->getKit($queue);
				$kit->giveTo($p);
			}

			$this->setCanHitPlayer(true);

			PracticeUtil::setFrozen($p, true, true);

			$ranked = $grp->isRanked() ? 'Ranked' : 'Unranked';
			$countdown = DuelGroup::MAX_COUNTDOWN_SEC;

			$p->sendMessage(PracticeUtil::str_replace(PracticeUtil::getMessage('duels.start.msg2'), ['%map%' => $grp->getArenaName()]));
			$p->sendMessage(PracticeUtil::str_replace(PracticeUtil::getMessage('duels.start.msg1'), ['%seconds%' => $countdown, '%ranked%' => $ranked, '%queue%' => $queue, '%player%' => $oppName]));
		}
	}

	/**
	 * @param Form  $form
	 * @param array $addedContent
	 *
	 * @return void
	 */
	public function sendForm(Form $form, array $addedContent = []) : void{
		if($this->isOnline() and !$this->isLookingAtForm){

			$p = $this->getPlayer();

			$formToJSON = $form->jsonSerialize();

			$content = [];

			if(isset($formToJSON['content']) and is_array($formToJSON['content']))
				$content = $formToJSON['content'];
			elseif(isset($formToJSON['buttons']) and is_array($formToJSON['buttons']))
				$content = $formToJSON['buttons'];

			if(!empty($addedContent))
				$content = array_replace($content, $addedContent);

			$exec = true;

			if($form instanceof SimpleForm){
				$title = $form->getTitle();

				$ffaTitle = FormUtil::getFFAForm()->getTitle();

				$ranked = null;

				if(isset($addedContent['ranked']))
					$ranked = boolval($addedContent['ranked']);

				$duelsTitle = FormUtil::getMatchForm()->getTitle();

				if($ranked !== null)
					$duelsTitle = FormUtil::getMatchForm($ranked)->getTitle();
			}

			if($exec === true){
				$this->currentFormData = $content;
				$this->isLookingAtForm = true;
				$p->sendForm($form);
			}else $this->sendMessage(TextFormat::RED . 'Failed to open form.');
		}
	}

	/**
	 * @return array
	 */
	public function removeForm() : array{
		$this->isLookingAtForm = false;
		$data = $this->currentFormData;
		$this->currentFormData = [];
		return $data;
	}

	/**
	 * @return array
	 */
	public function getDeviceInfo() : array{
		$title = TextFormat::GOLD . '   » ' . TextFormat::BOLD . TextFormat::BLUE . 'Info of ' . $this->playerName . TextFormat::RESET . TextFormat::GOLD . ' «';

		$deviceOS = TextFormat::GOLD . '   » ' . TextFormat::AQUA . 'Device-OS' . TextFormat::WHITE . ': ' . $this->getDeviceToStr(true) . TextFormat::GOLD . ' «';

		$deviceModel = TextFormat::GOLD . '   » ' . TextFormat::AQUA . 'Device-Model' . TextFormat::WHITE . ': ' . $this->deviceModel . TextFormat::GOLD . ' «';

		$c = 'Unknown';

		switch($this->input){
			case PracticeUtil::CONTROLS_CONTROLLER:
				$c = 'Controller';
				break;
			case PracticeUtil::CONTROLS_MOUSE:
				$c = 'Mouse';
				break;
			case PracticeUtil::CONTROLS_TOUCH:
				$c = 'Touch';
				break;
		}

		$deviceInput = TextFormat::GOLD . '   » ' . TextFormat::AQUA . 'Device-Input' . TextFormat::WHITE . ': ' . $c . TextFormat::GOLD . ' «';
		$numReports = count(PracticeCore::getReportHandler()->getReportsOf($this->playerName));
		$numOfReports = TextFormat::GOLD . '   » ' . TextFormat::AQUA . 'Times-Reported' . TextFormat::WHITE . ': ' . $numReports . TextFormat::GOLD . ' «';
		$arr = [$title, $deviceOS, $deviceModel, $deviceInput, $numOfReports];
		$lineSeparator = TextFormat::GRAY . PracticeUtil::getLineSeparator($arr);

		return [$title, $lineSeparator, $deviceOS, $deviceModel, $deviceInput, $numOfReports, $lineSeparator];
	}

	/**
	 * @param bool $forInfo
	 *
	 * @return string
	 */
	public function getDeviceToStr(bool $forInfo = false) : string{
		$str = 'Unknown';

		switch($this->deviceOs){
			case PracticeUtil::ANDROID:
				$str = 'Android';
				break;
			case PracticeUtil::IOS:
				$str = 'iOS';
				break;
			case PracticeUtil::MAC_EDU:
				$str = 'MacOS';
				break;
			case PracticeUtil::FIRE_EDU:
				$str = 'FireOS';
				break;
			case PracticeUtil::GEAR_VR:
				$str = 'GearVR';
				break;
			case PracticeUtil::HOLOLENS_VR:
				$str = 'HoloVR';
				break;
			case PracticeUtil::WINDOWS_10:
				$str = 'Win10';
				break;
			case PracticeUtil::WINDOWS_32:
				$str = 'Win32';
				break;
			case PracticeUtil::DEDICATED:
				$str = 'Dedic.';
				break;
			case PracticeUtil::ORBIS:
				$str = 'Orb';
				break;
			case PracticeUtil::NX:
				$str = 'NX';
				break;
		}

		if($this->input === PracticeUtil::CONTROLS_CONTROLLER and $forInfo === false)
			$str = 'Controller';

		return ($forInfo === false) ? TextFormat::WHITE . '[' . TextFormat::GREEN . $str . TextFormat::WHITE . ']' : $str;
	}

	/**
	 * @param $object
	 *
	 * @return bool
	 */
	#[Pure] public function equals($object) : bool{
		$result = false;

		if($object instanceof PracticePlayer) $result = $object->getPlayerName() === $this->playerName;

		return $result;
	}

	/* --------------------------------------------- ANTI CHEAT FUNCTIONS ---------------------------------------------*/

	/**
	 * @param string $msg
	 *
	 * @return void
	 */
	public function kick(string $msg) : void{
		if($this->isOnline()){
			$p = $this->getPlayer();
			$p->getInventory()->clearAll();
			$p->kick($msg);
		}
	}
}