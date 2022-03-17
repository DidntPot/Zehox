<?php

declare(strict_types=1);

namespace practice\scoreboard;

use pocketmine\scheduler\Task;
use practice\player\PracticePlayer;

class UpdateScoreboardTask extends Task
{
    /** @var PracticePlayer */
    private PracticePlayer $player;

    /**
     * @param PracticePlayer|null $player
     */
    public function __construct(PracticePlayer $player = null)
    {
        if (!is_null($player) and $player->isOnline()) $this->player = $player;
    }

    /**
     * @return void
     */
    public function onRun(): void
    {
        ScoreboardUtil::updateSpawnScoreboards($this->player);
    }
}