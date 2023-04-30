<?php

declare(strict_types=1);

namespace practice\player;

use pocketmine\scheduler\Task;
use practice\PracticeUtil;

class RespawnTask extends Task{
	/** @var PracticePlayer */
	private PracticePlayer $player;

	/**
	 * @param PracticePlayer $p
	 */
	public function __construct(PracticePlayer $p){
		$this->player = $p;
	}

	/**
	 * @return void
	 */
	public function onRun() : void{
		PracticeUtil::respawnPlayer($this->player);
	}
}