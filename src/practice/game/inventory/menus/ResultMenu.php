<?php

namespace practice\game\inventory\menus;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\player\Player;
use practice\duels\misc\DuelInvInfo;
use practice\PracticeUtil;

class ResultMenu{
	/**
	 * @param Player      $player
	 * @param DuelInvInfo $info
	 *
	 * @return void
	 */
	public static function showMenu(Player $player, DuelInvInfo $info){
		$menu = InvMenu::create(InvMenuTypeIds::TYPE_DOUBLE_CHEST);

		$name = PracticeUtil::getName('duel-result-inventory');
		$name = PracticeUtil::str_replace($name, ['%player%' => $info->getPlayerName()]);

		$menu->setName($name);
		$menu->setListener(InvMenu::readonly());

		$allItems = [];

		$count = 0;

		$row = 0;

		$maxRows = 3;

		$items = $info->getItems();

		foreach($items as $item){

			$currentRow = $maxRows - $row;
			$v = ($currentRow + 1) * 9;

			if($row === 0){
				$v = $v - 9;
				$val = intval(($count % 9) + $v);
			}else $val = $count - 9;

			if($val != -1) $allItems[$val] = $item;

			$count++;

			if($count % 9 == 0 and $count != 0) $row++;
		}

		$row = $maxRows + 1;
		$lastRowIndex = ($row + 1) * 9;
		$secLastRowIndex = $row * 9;

		$armorItems = $info->getArmor();

		foreach($armorItems as $armor){
			$allItems[$secLastRowIndex] = $armor;
			$secLastRowIndex++;
		}

		$statsItems = $info->getStatsItems();

		foreach($statsItems as $statsItem){
			$allItems[$lastRowIndex] = $statsItem;
			$lastRowIndex++;
		}

		$keys = array_keys($allItems);

		foreach($keys as $index){
			$index = intval($index);
			$item = $allItems[$index];
			$menu->getInventory()->setItem($index, $item);
		}

		$menu->send($player);
	}
}