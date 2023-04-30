<?php

declare(strict_types=1);

namespace practice\game\inventory;

use practice\duels\misc\DuelInvInfo;
use practice\game\inventory\menus\DuelMenu;
use practice\game\inventory\menus\FFAMenu;
use practice\game\inventory\menus\LeaderboardMenu;
use practice\game\inventory\menus\MatchMenu;
use practice\game\inventory\menus\ResultMenu;
use practice\PracticeCore;
use practice\PracticeUtil;

class InventoryUtil{
	/**
	 * @param $player
	 *
	 * @return void
	 */
	public static function sendFFAInv($player) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){
			$p = PracticeCore::getPlayerHandler()->getPlayer($player);
			FFAMenu::showMenu($p->getPlayer());
		}
	}

	/**
	 * @param      $player
	 * @param bool $ranked
	 *
	 * @return void
	 */
	public static function sendMatchInv($player, bool $ranked = false) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){
			$p = PracticeCore::getPlayerHandler()->getPlayer($player);
			MatchMenu::showMenu($p->getPlayer(), $ranked);
		}
	}

	/**
	 * @param $player
	 *
	 * @return void
	 */
	public static function sendDuelInv($player) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){
			$p = PracticeCore::getPlayerHandler()->getPlayer($player);
			DuelMenu::showMenu($p->getPlayer());
		}
	}

	/**
	 * @param        $player
	 * @param string $name
	 *
	 * @return void
	 */
	public static function sendResultInv($player, string $name) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){

			$p = PracticeCore::getPlayerHandler()->getPlayer($player);

			$playerName = $p->getPlayerName();

			$invName = PracticeUtil::getUncoloredString($name);

			$res = 'opponent';

			if(PracticeUtil::str_contains($invName, $playerName)) $res = 'player';

			if($p->hasInfoOfLastDuel()){

				$lastDuelInfo = $p->getInfoOfLastDuel()[$res];

				if($lastDuelInfo instanceof DuelInvInfo){
					ResultMenu::showMenu($player, $lastDuelInfo);
				}
			}
		}
	}

	/**
	 * @param $player
	 *
	 * @return void
	 */
	public static function sendLeaderboardInv($player) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){
			$p = PracticeCore::getPlayerHandler()->getPlayer($player);
			LeaderboardMenu::showMenu($p->getPlayer());
		}
	}
}