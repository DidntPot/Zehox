<?php

namespace practice\game\inventory\menus;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\player\Player;
use practice\game\items\PracticeItem;
use practice\PracticeCore;
use practice\PracticeUtil;

class DuelMenu{
	/**
	 * @param Player $player
	 *
	 * @return void
	 */
	public static function showMenu(Player $player){
		$menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);

		$menu->setName(PracticeUtil::getName('title-duel-inventory'));
		$menu->setListener(InvMenu::readonly());

		$items = PracticeCore::getItemHandler()->getDuelItems();

		foreach($items as $item){
			if($item instanceof PracticeItem){
				$slot = $item->getSlot();
				$i = $item->getItem();
				$menu->getInventory()->setItem($slot, $i);
			}
		}

		// I'm not fully implementing this.
		$menu->send($player);
	}
}