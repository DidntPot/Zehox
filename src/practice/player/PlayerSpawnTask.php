<?php

declare(strict_types=1);

namespace practice\player;

use pocketmine\scheduler\Task;
use practice\PracticeCore;
use practice\scoreboard\ScoreboardUtil;

class PlayerSpawnTask extends Task
{
    /** @var PracticePlayer */
    private PracticePlayer $player;

    /**
     * @param PracticePlayer $player
     */
    public function __construct(PracticePlayer $player)
    {
        $this->player = $player;
    }

    /**
     * @return void
     */
    public function onRun(): void
    {
        PracticeCore::getItemHandler()->spawnHubItems($this->player, true);
        ScoreboardUtil::updateSpawnScoreboards($this->player);
    }
}