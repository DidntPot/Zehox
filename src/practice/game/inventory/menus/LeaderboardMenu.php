<?php

namespace practice\game\inventory\menus;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\player\Player;
use practice\game\items\PracticeItem;
use practice\PracticeCore;
use practice\PracticeUtil;

class LeaderboardMenu{
	/**
	 * @param Player $player
	 *
	 * @return void
	 */
	public static function showMenu(Player $player){
		$menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);

		$menu->setName(PracticeUtil::getName('title-leaderboard-inv'));
		$menu->setListener(InvMenu::readonly());

		$items = PracticeCore::getItemHandler()->getLeaderboardItems();

		foreach($items as $item){
			if($item instanceof PracticeItem){
				$slot = $item->getSlot();
				$i = $item->getItem();
				$menu->getInventory()->setItem($slot, $i);
			}
		}

		$menu->send($player);
	}
}