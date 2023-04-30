<?php

declare(strict_types=1);

namespace practice\duels\misc;

use pocketmine\math\AxisAlignedBB;
use pocketmine\player\Player;
use pocketmine\world\Position;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;

class DuelSpectator{
	/** @var string */
	private string $name;
	/** @var AxisAlignedBB */
	private AxisAlignedBB $boundingBox;

	/**
	 * @param Player $player
	 */
	public function __construct(Player $player){
		$this->name = $player->getName();
		$this->boundingBox = $player->getBoundingBox();

		$player->boundingBox->contract($player->getSize()->getWidth(), 0, $player->getSize()->getHeight());

		PracticeUtil::setInSpectatorMode($player, true, true);
		PracticeCore::getItemHandler()->spawnSpecItems($player);
	}

	/**
	 * @param Position $pos
	 *
	 * @return void
	 */
	public function teleport(Position $pos) : void{
		if($this->isOnline()){
			$p = $this->getPlayer()->getPlayer();
			$p->teleport($pos);
		}
	}

	/**
	 * @return bool
	 */
	public function isOnline() : bool{
		$p = $this->getPlayer();
		return !is_null($p) and $p->isOnline();
	}

	/**
	 * @return PracticePlayer|null
	 */
	public function getPlayer() : ?PracticePlayer{
		return PracticeCore::getPlayerHandler()->getPlayer($this->name);
	}

	/**
	 * @param bool $disablePlugin
	 *
	 * @return void
	 */
	public function resetPlayer(bool $disablePlugin = false) : void{
		if($this->isOnline()){
			$p = $this->getPlayer()->getPlayer();
			$p->boundingBox = $this->boundingBox;
			PracticeUtil::resetPlayer($p, true, true, $disablePlugin);
		}
	}

	/**
	 * @param string $msg
	 *
	 * @return void
	 */
	public function sendMessage(string $msg) : void{
		if($this->isOnline()){
			$this->getPlayer()->sendMessage($msg);
		}
	}

	/**
	 * @param string $duration
	 *
	 * @return void
	 */
	public function update(string $duration) : void{
		if($this->isOnline()){
			$p = $this->getPlayer();
			$p->updateLineOfScoreboard(2, ' ' . $duration);
		}
	}

	/**
	 * @return string
	 */
	public function getPlayerName() : string{
		return $this->name;
	}
}