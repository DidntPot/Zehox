<?php

declare(strict_types=1);

namespace practice\anticheat;

use pocketmine\network\mcpe\protocol\types\GameMode;
use pocketmine\player\Player;
use pocketmine\Server;
use practice\PracticeCore;

class AntiCheatUtil{
	/** @var int */
	public const MAX_CHECK_FOR_PING = 50;
	/** @var float */
	public const MAX_REACH_DIST = 14.5;
	/** @var int */
	public const MAX_LETGO_REACH = 5;
	/** @var int */
	public const MAX_LETGO_CPS = 1;
	/** @var int */
	private const MAX_PING = 200;
	/** @var float */
	private const BLOCKS_AIR_LIMIT = 6.6;

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public static function canDamage($player) : bool{
		$result = false;
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){
			$p = PracticeCore::getPlayerHandler()->getPlayer($player);
			if($p->canHitPlayer())
				$result = $p->getNoDamageTicks() <= 0;
		}
		return $result;
	}

	/**
	 * @param Player $entity
	 * @param Player $damager
	 */
	public static function checkForReach(Player $entity, Player $damager) : void{
		if(!self::checkPing($damager) or $damager->getGamemode()->id() === GameMode::CREATIVE){
			return;
		}

		$distance = $damager->getPosition()->distance($entity->getPosition());
		if($distance > self::BLOCKS_AIR_LIMIT){
			self::sendReachLog($damager, $distance);
		}
	}

	/**
	 * @param Player $player
	 *
	 * @return bool
	 */
	private static function checkPing(Player $player) : bool{
		return $player->getNetworkSession()->getPing() < self::MAX_PING;
	}

	/**
	 * @param Player $player
	 * @param float  $distance
	 */
	private static function sendReachLog(Player $player, float $distance) : void{
		self::sendLog(
			"§7{$player->getName()} might be reaching! Distance: \n" . "§c" .
			round($distance, 1) . " §7(" . $player->getNetworkSession()->getPing() . " ms)"
		);
	}

	/**
	 * @param string $message
	 */
	private static function sendLog(string $message) : void{
		foreach(Server::getInstance()->getOnlinePlayers() as $player){
			if(!PracticeCore::getPlayerHandler()->isStaffMember($player->getName())){
				continue;
			}

			$player->sendTip("§8[§eAntiCheat§8] " . $message);
		}
	}
}