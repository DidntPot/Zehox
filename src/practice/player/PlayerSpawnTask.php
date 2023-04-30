<?php

declare(strict_types=1);

namespace practice\player;

use pocketmine\scheduler\Task;
use practice\PracticeCore;
use practice\PracticeUtil;
use practice\scoreboard\ScoreboardUtil;

class PlayerSpawnTask extends Task{
	/** @var PracticePlayer */
	private PracticePlayer $player;

	/**
	 * @param PracticePlayer $player
	 */
	public function __construct(PracticePlayer $player){
		$this->player = $player;
	}

	/**
	 * @return void
	 */
	public function onRun() : void{
		PracticeUtil::resetPlayer($this->player->getPlayer(), true);
		ScoreboardUtil::updateSpawnScoreboards($this->player);
	}
}