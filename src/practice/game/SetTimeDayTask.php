<?php

declare(strict_types=1);

namespace practice\game;

use pocketmine\scheduler\Task;
use practice\PracticeCore;

class SetTimeDayTask extends Task
{
    /** @var PracticeCore */
    private PracticeCore $core;

    /**
     * @param PracticeCore $core
     */
    public function __construct(PracticeCore $core)
    {
        $this->core = $core;
    }

    /**
     * @return void
     */
    public function onRun(): void
    {
        $levels = $this->core->getServer()->getWorldManager()->getWorlds();

        foreach ($levels as $level) {
            $level->setTime(6000);
            $level->stopTime();
        }
    }
}