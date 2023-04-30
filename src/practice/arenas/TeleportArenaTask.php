<?php

declare(strict_types=1);

namespace practice\arenas;

use pocketmine\scheduler\Task;
use practice\player\PracticePlayer;

class TeleportArenaTask extends Task{
	/** @var PracticePlayer */
	private PracticePlayer $player;
	/** @var FFAArena */
	private FFAArena $arena;

	/**
	 * @param PracticePlayer $player
	 * @param FFAArena       $arena
	 */
	public function __construct(PracticePlayer $player, FFAArena $arena){
		$this->player = $player;
		$this->arena = $arena;
	}

	/**
	 * @return void
	 */
	public function onRun() : void{
		$this->player->teleportToFFA($this->arena);
	}
}