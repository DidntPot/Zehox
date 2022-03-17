<?php

declare(strict_types=1);

namespace practice\game\inventory;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use practice\game\inventory\menus\inventories\PracBaseInv;
use practice\PracticeCore;

class InventoryTask extends Task
{
    /** @var string */
    private string $player;
    /** @var PracBaseInv */
    private PracBaseInv $inventory;

    /**
     * @param $player
     * @param PracBaseInv $inv
     */
    public function __construct($player, PracBaseInv $inv)
    {
        if (PracticeCore::getPlayerHandler()->isPlayerOnline($player)) {
            $p = PracticeCore::getPlayerHandler()->getPlayer($player);
            $this->player = $p->getPlayerName();
            $this->inventory = $inv;
        }
    }

    /**
     * @return void
     */
    public function onRun(): void
    {
        if (PracticeCore::getPlayerHandler()->isPlayerOnline($this->player)) {
            $p = PracticeCore::getPlayerHandler()->getPlayer($this->player);
            $this->inventory->onSendInvSuccess($p->getPlayer());
        } else {
            $this->inventory->onSendInvFail(Server::getInstance()->getPlayerExact($this->player));
        }
    }
}