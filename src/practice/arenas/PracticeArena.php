<?php

declare(strict_types=1);

namespace practice\arenas;

use JetBrains\PhpStorm\Pure;
use pocketmine\world\Position;
use pocketmine\world\World;
use practice\kits\Kit;
use practice\PracticeCore;
use practice\PracticeUtil;

abstract class PracticeArena{
	/** @var string */
	public const string DUEL_ARENA = "arena.duel";
	/** @var string */
	public const string FFA_ARENA = "arena.ffa";
	/** @var string */
	public const string SPLEEF_ARENA = "arena.spleef";
	/** @var string */
	public const string NO_ARENA = "none";
	/** @var World */
	protected World $world;

	/** @var array */
	protected array $kits = [];
	/** @var string */
	private string $name;
	/** @var string */
	private string $arenaType;
	/** @var Position */
	private Position $spawnPos;
	/** @var bool */
	private bool $build;

	/**
	 * @param string   $name
	 * @param string   $arenaType
	 * @param bool     $canBuild
	 * @param Position $center
	 */
	public function __construct(string $name, string $arenaType, bool $canBuild, Position $center){
		$this->name = $name;

		$this->kits = [];

		$this->arenaType = $arenaType;
		$this->build = $canBuild;
		$this->spawnPos = $center;
		$this->world = $center->getWorld();
	}

	/**
	 * @param string $test
	 *
	 * @return string
	 */
	#[Pure] public static function getType(string $test) : string{
		$result = "unknown";

		if(PracticeUtil::equals_string($test, "Spleef", "spleef", "SPLEEF"))
			$result = self::SPLEEF_ARENA;

		elseif(PracticeUtil::equals_string($test, "duel", "duels", "Duels", "Duel", "DUEL", "1vs1", "1v1"))
			$result = self::DUEL_ARENA;

		elseif(PracticeUtil::equals_string($test, "ffa", "FFA"))
			$result = self::FFA_ARENA;

		return $result;
	}

	/**
	 * @param string $type
	 *
	 * @return string
	 */
	public static function getFormattedType(string $type) : string{
		$str = "(Unknown)";

		if($type === self::FFA_ARENA)
			$str = "(FFA)";
		elseif($type === self::DUEL_ARENA)
			$str = "(Duel)";
		elseif($type === self::SPLEEF_ARENA)
			$str = "(Spleef)";

		return $str;
	}

	/**
	 * @param $kit
	 *
	 * @return $this
	 */
	public function addKit($kit) : PracticeArena{
		if($this->arenaType === self::DUEL_ARENA){
			if(is_array($kit)){
				foreach($kit as $k){
					if($k instanceof Kit){
						$name = $k->getName();
						if(!PracticeUtil::arr_contains_value($name, $this->kits)){
							$this->kits[] = $k->getName();
						}
					}elseif(is_string($k)){
						if($k !== Kit::NO_KIT and !PracticeUtil::arr_contains_value($k, $this->kits)) $this->kits[] = $k;
					}
				}
			}else{
				if($kit instanceof Kit){
					$name = $kit->getName();
					if(!PracticeUtil::arr_contains_value($name, $this->kits)) $this->kits[] = $kit->getName();
				}elseif(is_string($kit)){
					if(!PracticeUtil::arr_contains_value($kit, $this->kits)) $this->kits[] = $kit;
				}
			}
		}else{
			$this->setKit($kit);
		}

		return $this;
	}

	/**
	 * @param $kit
	 *
	 * @return $this
	 */
	public function setKit($kit) : PracticeArena{
		$kitName = null;

		if($kit instanceof Kit){
			$kitName = $kit->getName();
		}elseif(is_string($kit)){
			if(PracticeCore::getKitHandler()->isKit($kit)){
				$kitName = $kit;
			}
		}

		if(!is_null($kitName)){
			$this->kits = [$kitName];
		}
		return $this;
	}

	/**
	 * @param $kit
	 *
	 * @return void
	 */
	public function removeKit($kit) : void{
		if(PracticeUtil::arr_contains_value($kit, $this->kits)){
			$index = PracticeUtil::arr_indexOf($kit, $this->kits, true);
			unset($this->kits[$index]);
			$this->kits = array_values($this->kits);
		}
	}

	/**
	 * @return Kit|null
	 */
	public function getFirstKit() : ?Kit{
		return PracticeCore::getKitHandler()->getKit($this->kits[0]);
	}

	/**
	 * @return bool
	 */
	public function doesHaveKit() : bool{
		return count($this->kits) > 0 and PracticeCore::getKitHandler()->isKit($this->kits[0]);
	}

	/**
	 * @param string $str
	 *
	 * @return bool
	 */
	public function hasKit(string $str) : bool{
		$kit = $this->getKit($str);
		return !is_null($kit);
	}

	/**
	 * @param string $str
	 *
	 * @return Kit|null
	 */
	public function getKit(string $str) : ?Kit{
		$kit = null;

		$index = array_search($str, $this->kits, true);

		if(is_bool($index) and $index === false) $index = -1;

		if($index !== -1){
			$kitName = $this->kits[$index];
			if(PracticeCore::getKitHandler()->isKit($kitName))
				$kit = PracticeCore::getKitHandler()->getKit($kitName);
		}

		return $kit;
	}

	/**
	 * @return World
	 */
	public function getWorld() : World{
		return $this->world;
	}

	/**
	 * @return Position
	 */
	public function getSpawnPosition() : Position{
		return $this->spawnPos;
	}

	/**
	 * @return bool
	 */
	public function canBuild() : bool{
		return $this->build;
	}

	/**
	 * @return string
	 */
	public function getLocalizedName() : string{
		return strtolower(strval(str_replace(" ", "", $this->name)));
	}

	/**
	 * @param $arena
	 *
	 * @return bool
	 */
	#[Pure] public function equals($arena) : bool{
		$result = false;
		if($arena instanceof PracticeArena){
			if($arena->getArenaType() === $this->arenaType){
				$result = $arena->getName() === $this->name;
			}
		}
		return $result;
	}

	/**
	 * @return string
	 */
	public function getArenaType() : string{
		return $this->arenaType;
	}

	/**
	 * @return string
	 */
	public function getName() : string{
		return $this->name;
	}

	/**
	 * @return array
	 */
	public abstract function toMap() : array;

}