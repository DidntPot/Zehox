<?php

declare(strict_types=1);

namespace practice\arenas;

use JetBrains\PhpStorm\ArrayShape;
use pocketmine\entity\Location;
use pocketmine\world\Position;
use practice\kits\Kit;
use practice\PracticeUtil;

class DuelArena extends PracticeArena{
	/** @var Position */
	private Position $playerPos;
	/** @var Position */
	private Position $opponentPos;

	/** @var mixed|null */
	private mixed $playerPitch;
	/** @var mixed|null */
	private mixed $playerYaw;

	/** @var mixed|null */
	private mixed $oppPitch;
	/** @var mixed|null */
	private mixed $oppYaw;

	/**
	 * @param string        $name
	 * @param bool          $canBuild
	 * @param Position      $center
	 * @param               $kits
	 * @param Position|null $playerPos
	 * @param Position|null $oppPos
	 */
	public function __construct(string $name, bool $canBuild, Position $center, $kits = null, Position $playerPos = null, Position $oppPos = null){
		parent::__construct($name, self::DUEL_ARENA, $canBuild, $center);

		$this->playerPos = new Position($center->x - 6, $center->y, $center->z, $center->world);
		$this->opponentPos = new Position($center->x + 6, $center->y, $center->z, $center->world);
		$this->playerPitch = null;
		$this->playerYaw = null;
		$this->oppYaw = null;
		$this->oppPitch = null;

		if(!is_null($playerPos)){
			$this->playerPos = $playerPos;
			if($playerPos instanceof Location){
				$this->playerPitch = $playerPos->pitch;
				$this->playerYaw = $playerPos->yaw;
			}
		}

		if(!is_null($oppPos)){
			$this->opponentPos = $oppPos;
			if($oppPos instanceof Location){
				$this->oppPitch = $oppPos->pitch;
				$this->oppYaw = $oppPos->yaw;
			}
		}

		if(!is_null($kits)){
			if($kits instanceof Kit){
				$this->kits[] = $kits->getName();
			}elseif(is_array($kits)){

				$this->kits = [];
				$keys = array_keys($kits);

				foreach($keys as $key){
					$val = $kits[$key];
					if($val instanceof Kit){
						$this->kits[] = $val->getName();
					}elseif(is_string($val)){
						$this->kits[] = $val;
					}
				}
			}elseif(is_string($kits)){
				$this->kits[] = $kits;
			}
		}
	}

	/**
	 * @return array
	 */
	public function getKits() : array{
		return $this->kits;
	}

	/**
	 * @return array
	 */
	#[ArrayShape(["center" => "array", "level" => "string", "build" => "bool", "player-pos" => "array", "opponent-pos" => "array", "kits" => "array|mixed|string"])] public function toMap() : array{
		$result = [
			"center" => PracticeUtil::getPositionToMap($this->getSpawnPosition()),
			"level" => $this->world->getFolderName(),
			"build" => $this->canBuild(),
			"player-pos" => PracticeUtil::getPositionToMap($this->getPlayerPos()),
			"opponent-pos" => PracticeUtil::getPositionToMap($this->getOpponentPos())
		];

		$size = count($this->kits);

		if($size > 0){
			if($this->getArenaType() === self::DUEL_ARENA){
				$kit = $this->kits;
			}else{
				$kit = $this->kits[0];
			}
		}else{
			$kit = Kit::NO_KIT;
		}

		if(!is_null($kit)){
			$result["kits"] = $kit;
		}
		return $result;
	}

	/**
	 * @return Position|Location
	 */
	public function getPlayerPos() : Position|Location{
		$result = $this->playerPos;
		if(!is_null($this->playerYaw) and !is_null($this->playerPitch)){
			$result = new Location($this->playerPos->x, $this->playerPos->y, $this->playerPos->z, $this->world, $this->playerYaw, $this->playerPitch);
		}
		return $result;
	}

	/**
	 * @param $pos
	 *
	 * @return $this
	 */
	public function setPlayerPos($pos) : DuelArena{
		if($pos instanceof Position){
			if(PracticeUtil::areWorldEqual($pos->world, $this->world)){
				$this->playerPos = $pos;
			}
		}elseif($pos instanceof Location){
			if(PracticeUtil::areWorldEqual($pos->world, $this->world)){
				$this->playerPos = new Position($pos->x, $pos->y, $pos->z, $pos->world);
				$this->playerYaw = $pos->yaw;
				$this->playerPitch = $pos->pitch;
			}
		}
		return $this;
	}

	/**
	 * @return Position|Location
	 */
	public function getOpponentPos() : Position|Location{
		$result = $this->opponentPos;
		if(!is_null($this->oppYaw) and !is_null($this->oppPitch)){
			$result = new Location($this->opponentPos->x, $this->opponentPos->y, $this->opponentPos->z, $this->world, $this->oppYaw, $this->oppPitch);
		}
		return $result;
	}

	/**
	 * @param $pos
	 *
	 * @return $this
	 */
	public function setOpponentPos($pos) : DuelArena{
		if($pos instanceof Position){
			if(PracticeUtil::areWorldEqual($pos->world, $this->world)){
				$this->opponentPos = $pos;
			}
		}elseif($pos instanceof Location){
			if(PracticeUtil::areWorldEqual($pos->world, $this->world)){
				$this->opponentPos = new Position($pos->x, $pos->y, $pos->z, $pos->world);
				$this->oppYaw = $pos->yaw;
				$this->oppPitch = $pos->pitch;
			}
		}
		return $this;
	}
}