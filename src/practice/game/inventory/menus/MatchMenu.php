<?php

namespace practice\game\inventory\menus;

use muqsit\invmenu\InvMenu;
use muqsit\invmenu\type\InvMenuTypeIds;
use pocketmine\item\ItemIds;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use practice\game\items\PracticeItem;
use practice\PracticeCore;
use practice\PracticeUtil;

class MatchMenu{
	/**
	 * @param Player $player
	 * @param bool   $ranked
	 *
	 * @return void
	 */
	public static function showMenu(Player $player, bool $ranked = false){
		$menu = InvMenu::create(InvMenuTypeIds::TYPE_CHEST);

		$name = PracticeUtil::getName('inventory.select-duel');
		$name = PracticeUtil::str_replace($name, ['%ranked%' => ($ranked ? 'Ranked' : 'Unranked')]);

		$menu->setName($name);
		$menu->setListener(InvMenu::readonly());

		$items = PracticeCore::getItemHandler()->getDuelItems();

		foreach($items as $item){

			if($item instanceof PracticeItem){

				$name = $item->getName();

				$uncolored = PracticeUtil::getUncoloredString($name);

				$numInQueue = PracticeCore::getDuelHandler()->getNumQueuedFor($uncolored, $ranked);

				$numInFights = PracticeCore::getDuelHandler()->getNumFightsFor($uncolored, $ranked);

				$inQueues = "\n" . TextFormat::GREEN . 'In-Queues: ' . TextFormat::WHITE . $numInQueue;

				$inFights = "\n" . TextFormat::GREEN . 'In-Fights: ' . TextFormat::WHITE . $numInFights;

				$lore = [$inQueues, $inFights];

				$properCount = PracticeUtil::getProperCount($numInQueue);

				$slot = $item->getSlot();

				$i = clone $item->getItem();

				if($i->getId() === ItemIds::POTION) $properCount = 1;

				$i = $i->setLore($lore)->setCount($properCount);
				$menu->getInventory()->setItem($slot, $i);
			}
		}

		// I'm not fully implementing this.
		$menu->send($player);
	}
}