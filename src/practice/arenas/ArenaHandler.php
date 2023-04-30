<?php

declare(strict_types=1);

namespace practice\arenas;

use JetBrains\PhpStorm\Pure;
use JsonException;
use pocketmine\utils\Config;
use pocketmine\world\Position;
use practice\kits\Kit;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;

class ArenaHandler{
	/** @var string */
	private string $configPath;

	/* @var Config */
	private Config $config;

	/** @var array */
	private array $closedArenas;

	/* @var DuelArena[]|array */
	private array $duelArenas;

	/* @var FFAArena[]|array */
	private array $ffaArenas;

	/**
	 * @throws JsonException
	 */
	public function __construct(){
		$this->configPath = PracticeCore::getInstance()->getDataFolder() . "/arenas.yml";
		$this->initConfig();

		$this->closedArenas = [];

		$this->initArenas();

		$combined_arrays = array_merge($this->getFFAArenas(), $this->getDuelArenas());

		foreach($combined_arrays as $value){
			if($value instanceof PracticeArena){
				$name = $value->getName();
				$this->closedArenas[$name] = false;
			}
		}
	}

	/**
	 * @return void
	 * @throws JsonException
	 */
	private function initConfig() : void{
		$this->config = new Config($this->configPath, Config::YAML, []);

		$edited = false;

		if(!$this->config->exists("duel-arenas")){
			$this->config->set("duel-arenas", []);
			$edited = true;
		}

		if(!$this->config->exists("ffa-arenas")){
			$this->config->set("ffa-arenas", []);
			$edited = true;
		}

		if($edited === true) $this->config->save();
	}

	/**
	 * @return void
	 */
	private function initArenas() : void{
		$this->ffaArenas = [];
		$this->duelArenas = [];

		$ffaKeys = $this->getConfig()->get("ffa-arenas");

		$ffaArenaKeys = array_keys($ffaKeys);

		foreach($ffaArenaKeys as $key){
			$key = strval($key);
			if($this->isFFAArenaFromConfig($key)){
				$arena = $this->getFFAArenaFromConfig($key);
				$this->ffaArenas[$key] = $arena;
			}
		}

		$duelKeys = $this->getConfig()->get("duel-arenas");

		$duelArenaKeys = array_keys($duelKeys);

		foreach($duelArenaKeys as $key){
			$key = strval($key);
			if($this->isDuelArenaFromConfig($key)){
				$arena = $this->getDuelArenaFromConfig($key);
				$this->duelArenas[$key] = $arena;
			}
		}
	}

	/**
	 * @return Config
	 */
	private function getConfig() : Config{
		return $this->config;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	private function isFFAArenaFromConfig(string $name) : bool{
		return !is_null($this->getFFAArenaFromConfig($name));
	}

	/**
	 * @param string $name
	 *
	 * @return FFAArena|null
	 */
	private function getFFAArenaFromConfig(string $name) : ?FFAArena{
		$ffaArenas = $this->getConfig()->get("ffa-arenas");

		$result = null;

		if(isset($ffaArenas[$name])){
			$arena = $ffaArenas[$name];
			$arenaKit = Kit::NO_KIT;
			$arenaBuild = false;
			$arenaSpawn = null;

			$foundArena = false;

			if(PracticeUtil::arr_contains_keys($arena, "build", "spawn", "level", "kit")){
				$kit = $arena["kit"];
				$canBuild = $arena["build"];
				$spawnArr = $arena["spawn"];
				$level = (string) $arena["level"];

				$arenaSpawn = new Position($spawnArr["x"], $spawnArr["y"], $spawnArr["z"], PracticeCore::getInstance()->getServer()->getWorldManager()->getWorldByName($level));

				$arenaBuild = boolval($canBuild);

				if($kit !== Kit::NO_KIT) $arenaKit = strval($kit);

				if(!is_null($arenaSpawn)) $foundArena = true;
			}

			if($foundArena)
				$result = new FFAArena($name, $arenaBuild, $arenaSpawn, $arenaKit);
		}
		return $result;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	private function isDuelArenaFromConfig(string $name) : bool{
		return !is_null($this->getDuelArenaFromConfig($name));
	}

	/**
	 * @param string $name
	 *
	 * @return DuelArena|null
	 */
	private function getDuelArenaFromConfig(string $name) : ?DuelArena{
		$duelArenas = $this->getConfig()->get("duel-arenas");
		$result = null;

		if(isset($duelArenas[$name])){
			$arena = $duelArenas[$name];
			$arenaCenter = null;
			$playerPos = null;
			$oppPos = null;
			$build = false;
			$kits = [];

			$foundArena = false;

			if(PracticeUtil::arr_contains_keys($arena, "center", "build", "level", "player-pos", "opponent-pos", "kits")){
				$cfgKits = $arena["kits"];
				$canBuild = $arena["build"];
				$centerArr = $arena["center"];
				$level = $arena["level"];
				$cfgPlayerPos = $arena["player-pos"];
				$cfgOppPos = $arena["opponent-pos"];

				if(!is_null($cfgKits)){
					if(is_array($cfgKits)){
						foreach($cfgKits as $kit){
							if($kit !== Kit::NO_KIT){
								$kits[] = strval($kit);
							}
						}
					}elseif(is_string($cfgKits)){
						$k = $cfgKits;
						if($k !== Kit::NO_KIT){
							$kits[] = $k;
						}
					}
				}

				$arenaCenter = PracticeUtil::getPositionFromMap($centerArr, $level);
				$playerPos = PracticeUtil::getPositionFromMap($cfgPlayerPos, $level);
				$oppPos = PracticeUtil::getPositionFromMap($cfgOppPos, $level);

				if(is_bool($canBuild)) $build = $canBuild;

				if(!is_null($arenaCenter) and !is_null($playerPos) and !is_null($oppPos)) $foundArena = true;
			}

			if($foundArena){
				$result = new DuelArena($name, $build, $arenaCenter, $kits, $playerPos, $oppPos);
			}
		}
		return $result;
	}

	/**
	 * @return FFAArena[]
	 */
	public function getFFAArenas() : array{
		return $this->ffaArenas;
	}

	/**
	 * @return DuelArena[]
	 */
	public function getDuelArenas() : array{
		return $this->duelArenas;
	}

	/**
	 * @param string   $name
	 * @param Position $pos
	 * @param string   $arenaType
	 *
	 * @return void
	 * @throws JsonException
	 */
	public function createArena(string $name, Position $pos, string $arenaType = PracticeArena::FFA_ARENA) : void{
		if($arenaType === PracticeArena::DUEL_ARENA) $this->createDuelArena($name, $pos);
		elseif($arenaType === PracticeArena::FFA_ARENA) $this->createFFAArena($name, $pos);

		$this->closedArenas[$name] = false;
	}

	/**
	 * @param string   $name
	 * @param Position $pos
	 *
	 * @return void
	 * @throws JsonException
	 */
	private function createDuelArena(string $name, Position $pos) : void{
		$arena = new DuelArena($name, false, $pos);

		$this->duelArenas[$name] = $arena;

		$map = $arena->toMap();
		$duelArenas = $this->getConfig()->get("duel-arenas");

		if(!isset($duelArenas[$name])){
			$duelArenas[$name] = $map;
			$this->getConfig()->set("duel-arenas", $duelArenas);
			$this->getConfig()->save();
		}
	}

	/**
	 * @param string   $name
	 * @param Position $pos
	 *
	 * @return void
	 * @throws JsonException
	 */
	private function createFFAArena(string $name, Position $pos) : void{
		$arena = new FFAArena($name, false, $pos);

		$this->ffaArenas[$name] = $arena;

		$map = $arena->toMap();
		$ffaArenas = $this->getConfig()->get("ffa-arenas");
		if(!isset($ffaArenas[$name])){
			$ffaArenas[$name] = $map;
			$this->getConfig()->set("ffa-arenas", $ffaArenas);
			$this->getConfig()->save();
		}
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 * @throws JsonException
	 */
	public function removeArena(string $name) : bool{
		$result = false;

		if($this->isDuelArena($name)){
			$this->removeDuelArena($name);
			$result = true;
		}elseif($this->isFFAArena($name)){
			$this->removeFFAArena($name);
			$result = true;
		}

		if($result === true){
			if(isset($this->closedArenas[$name]))
				unset($this->closedArenas[$name]);
		}

		return $result;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isDuelArena(string $name) : bool{
		return isset($this->duelArenas[$name]);
	}

	/**
	 * @param string $name
	 *
	 * @return void
	 * @throws JsonException
	 */
	private function removeDuelArena(string $name) : void{
		$duelArenas = $this->getConfig()->get("duel-arenas");

		if(isset($duelArenas[$name])){
			unset($duelArenas[$name], $this->duelArenas[$name]);
			$this->getConfig()->set("duel-arenas", $duelArenas);
			$this->getConfig()->save();
		}
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isFFAArena(string $name) : bool{
		return isset($this->ffaArenas[$name]);
	}

	/**
	 * @param string $name
	 *
	 * @return void
	 * @throws JsonException
	 */
	private function removeFFAArena(string $name) : void{
		$ffaArenas = $this->getConfig()->get("ffa-arenas");

		if(isset($ffaArenas[$name])){
			unset($ffaArenas[$name], $this->ffaArenas[$name]);
			$this->getConfig()->set("ffa-arenas", $ffaArenas);
			$this->getConfig()->save();
		}
	}

	/**
	 * @param string        $name
	 * @param PracticeArena $arena
	 *
	 * @return bool
	 * @throws JsonException
	 */
	public function updateArena(string $name, PracticeArena $arena) : bool{
		$result = false;

		if($arena->getArenaType() === PracticeArena::FFA_ARENA){
			if($this->isFFAArena($name)){
				$result = true;
				$ffaArenas = $this->getConfig()->get("ffa-arenas");

				$this->ffaArenas[$name] = $arena;

				$map = $arena->toMap();
				$ffaArenas[$name] = $map;
				$this->getConfig()->set("ffa-arenas", $ffaArenas);
				$this->getConfig()->save();
			}
		}else if($arena->getArenaType() === PracticeArena::DUEL_ARENA){
			if($this->isDuelArena($name)){
				$result = true;

				$this->duelArenas[$name] = $arena;

				$duelArenas = $this->getConfig()->get("duel-arenas");
				$map = $arena->toMap();
				$duelArenas[$name] = $map;
				$this->getConfig()->set("duel-arenas", $duelArenas);
				$this->getConfig()->save();
			}
		}

		return $result;
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function getArena(string $name) : mixed{
		$result = null;
		if($this->isDuelArena($name)){
			$result = $this->getDuelArena($name);
		}elseif($this->isFFAArena($name)){
			$result = $this->getFFAArena($name);
		}
		return $result;
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function getDuelArena(string $name) : mixed{
		$result = null;

		if(isset($this->duelArenas[$name]))
			$result = $this->duelArenas[$name];

		return $result;
	}

	/**
	 * @param string $name
	 *
	 * @return mixed
	 */
	public function getFFAArena(string $name) : mixed{

		$result = null;

		if(isset($this->ffaArenas[$name]))
			$result = $this->ffaArenas[$name];

		return $result;
	}

	/**
	 * @param Position $pos
	 *
	 * @return DuelArena|FFAArena|PracticeArena|null
	 */
	public function getArenaClosestTo(Position $pos) : DuelArena|FFAArena|PracticeArena|null{
		$arenas = array_merge($this->getDuelArenas(), $this->getFFAArenas());

		$greatest = null;

		$closestDistance = -1.0;

		if(!is_null($pos)){
			foreach($arenas as $arena){
				if($arena instanceof PracticeArena){
					$posLevel = $pos->getWorld();
					$arenaLevel = $arena->getWorld();
					if(PracticeUtil::areWorldEqual($posLevel, $arenaLevel)){
						$center = $arena->getSpawnPosition();
						$currentDistance = $center->distance($pos);
						if($closestDistance === -1.0){
							$closestDistance = $currentDistance;
							$greatest = $arena;
						}else{
							if($currentDistance < $closestDistance){
								$closestDistance = $currentDistance;
								$greatest = $arena;
							}
						}
					}
				}
			}
		}

		return $greatest;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function doesArenaExist(string $name) : bool{
		return $this->isDuelArena($name) or $this->isFFAArena($name);
	}

	/**
	 * @param bool $listAll
	 *
	 * @return string
	 */
	#[Pure] public function getArenaList(bool $listAll) : string{
		$result = "Arena list: ";

		$duelArenas = $this->getDuelArenas();
		$ffaArenas = $this->getFFAArenas();

		$allArenas = ($listAll === true) ? array_merge($ffaArenas, $duelArenas) : $ffaArenas;

		$len = count($allArenas) - 1;
		$count = 0;
		foreach($allArenas as $arena){
			if(!is_null($arena) and $arena instanceof PracticeArena){
				$comma = ($count === $len ? "" : ", ");
				$arenaType = PracticeArena::getFormattedType($arena->getArenaType());
				$result .= $arena->getName() . " " . $arenaType . $comma;
			}
			$count++;
		}

		return $result;
	}

	/**
	 * @param $arena
	 *
	 * @return void
	 */
	public function setArenaClosed($arena) : void{
		$name = null;
		if(isset($arena) and !is_null($arena)){
			if($arena instanceof PracticeArena){
				$name = $arena->getName();
			}elseif(is_string($arena)){
				$name = $arena;
			}
		}

		if(!is_null($name)){
			if(!$this->isArenaClosed($name)) $this->closedArenas[$name] = true;
		}
	}

	/**
	 * @param string $arena
	 *
	 * @return bool
	 */
	public function isArenaClosed(string $arena) : bool{
		return isset($this->closedArenas[$arena]) and $this->closedArenas[$arena] === true;
	}

	/**
	 * @param $arena
	 *
	 * @return void
	 */
	public function setArenaOpen($arena) : void{
		$name = null;

		if(isset($arena) and !is_null($arena)){
			if($arena instanceof PracticeArena){
				$name = $arena->getName();
			}elseif(is_string($arena)){
				$name = $arena;
			}
		}

		if(!is_null($name)){
			if($this->isArenaClosed($name)){
				$this->closedArenas[$name] = false;
			}
		}
	}

	/**
	 * @param string $arena
	 *
	 * @return int
	 */
	public function getNumPlayersInArena(string $arena) : int{
		$result = 0;
		$players = PracticeCore::getPlayerHandler()->getOnlinePlayers();
		foreach($players as $p){
			if($p instanceof PracticePlayer and $p->isInArena()){
				$a = $p->getCurrentArena();
				$name = $a->getName();
				if($name === $arena) $result++;
			}
		}
		return $result;
	}
}