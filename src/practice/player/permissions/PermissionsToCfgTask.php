<?php

declare(strict_types=1);

namespace practice\player\permissions;

use JsonException;
use pocketmine\scheduler\Task;
use practice\PracticeCore;

class PermissionsToCfgTask extends Task
{
    public function __construct(){}

    /**
     * @return void
     * @throws JsonException
     */
    public function onRun(): void
    {
        PracticeCore::getPermissionHandler()->initPermissions();
    }
}