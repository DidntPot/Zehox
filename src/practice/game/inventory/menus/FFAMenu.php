<?php

namespace practice\game\inventory\menus;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use practice\game\items\PracticeItem;
use practice\PracticeCore;
use practice\PracticeUtil;

class FFAMenu
{
    /**
     * @param Player $player
     * @return void
     */
    public static function showMenu(Player $player)
    {
        $menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);

        $menu->setName(PracticeUtil::getName('inventory.select-ffa'));
        $menu->setListener(InvMenu::readonly());

        $items = PracticeCore::getItemHandler()->getFFAItems();

        foreach ($items as $item) {
            if ($item instanceof PracticeItem) {
                $i = clone $item->getItem();
                $name = PracticeUtil::getUncoloredString($item->getName());
                $numPlayers = PracticeCore::getArenaHandler()->getNumPlayersInArena($name);
                $lore = ["\n" . TextFormat::GREEN . 'Players: ' . $numPlayers];

                $properCount = PracticeUtil::getProperCount($numPlayers);

                if ($i->getId() === ItemIds::POTION) $properCount = 1;

                $i = $i->setLore($lore)->setCount($properCount);

                $slot = $item->getSlot();
                $menu->getInventory()->setItem($slot, $i);
            }
        }

        // I'm not fully implementing this.
        $menu->send($player);
    }
}