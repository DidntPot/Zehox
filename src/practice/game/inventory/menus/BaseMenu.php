<?php

declare(strict_types=1);

namespace practice\game\inventory\menus;

use JetBrains\PhpStorm\Pure;
use pocketmine\inventory\transaction\action\SlotChangeAction;
use pocketmine\player\Player;
use practice\game\inventory\menus\inventories\PracBaseInv;
use practice\player\PracticePlayer;

abstract class BaseMenu
{
    /** @var bool */
    private bool $edit;
    /** @var string */
    private string $name;
    /** @var PracBaseInv */
    private PracBaseInv $inv;

    /**
     * @param PracBaseInv $inv
     */
    #[Pure] public function __construct(PracBaseInv $inv)
    {
        $this->inv = $inv;
        $this->edit = true;
        $this->name = $inv->getName();
    }

    /**
     * @param bool $edit
     * @return $this
     */
    public function setEdit(bool $edit): BaseMenu
    {
        $this->edit = $edit;
        return $this;
    }

    /**
     * @return bool
     */
    public function canEdit(): bool
    {
        return $this->edit;
    }

    /**
     * @param Player $player
     * @param string|null $customName
     * @return bool
     */
    public function send(Player $player, ?string $customName = null): bool
    {
        return $this->getInventory()->send($player, ($customName !== null ? $customName : $this->getName()));
    }

    /**
     * @return PracBaseInv
     */
    public function getInventory(): PracBaseInv
    {
        return $this->inv;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return $this
     */
    public function setName(string $name): BaseMenu
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param PracticePlayer $p
     * @param SlotChangeAction $action
     * @return void
     */
    abstract public function onItemMoved(PracticePlayer $p, SlotChangeAction $action): void;

    /**
     * @param Player $player
     * @return void
     */
    abstract public function onInventoryClosed(Player $player): void;

    /**
     * @param $player
     * @return void
     */
    abstract public function sendTo($player): void;
}