<?php

declare(strict_types=1);

namespace practice\scoreboard;

use pocketmine\Server;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;

class ScoreboardUtil{
	/**
	 * @param PracticePlayer|null $player
	 *
	 * @return void
	 */
	public static function updateSpawnScoreboards(PracticePlayer $player = null) : void{
		$playerHandler = PracticeCore::getPlayerHandler();
		$onlinePlayers = $playerHandler->getOnlinePlayers();
		$server = Server::getInstance();

		$numOnline = count($server->getOnlinePlayers());
		$maxPlayers = $server->getMaxPlayers();
		$inFights = $playerHandler->getPlayersInFights();
		$duelHandler = PracticeCore::getDuelHandler();
		$inQueues = $duelHandler->getNumberOfQueuedPlayers();
		$names = self::getNames();

		$onlineStr = PracticeUtil::str_replace($names['online'], ['%num%' => $numOnline, '%max-num%' => $maxPlayers]);
		$inFightsStr = PracticeUtil::str_replace($names['in-fights'], ['%num%' => $inFights]);
		$inQueuesStr = PracticeUtil::str_replace($names['in-queues'], ['%num%' => $inQueues]);

		$strarr = [1 => $onlineStr, 2 => $inFightsStr, 3 => $inQueuesStr];

		$keys = array_keys($strarr);

		foreach($onlinePlayers as $online){
			$exec = !($player !== null) || !$online->equals($player);
			if($exec === true and ($online->getScoreboard() === 'scoreboard.spawn')){
				foreach($keys as $key){
					$key = intval($key);
					$val = ' ' . $strarr[$key];
					$online->updateLineOfScoreboard($key, $val);
				}
			}
		}
	}

	/**
	 * @return array
	 */
	public static function getNames() : array{
		return [
			'player-cps' => PracticeUtil::getName('scoreboard.player.cps'),
			'opponent-cps' => PracticeUtil::getName('scoreboard.opponent.cps'),
			'kills' => PracticeUtil::getName('scoreboard.arena-ffa.kills'),
			'deaths' => PracticeUtil::getName('scoreboard.arena-ffa.deaths'),
			'arena' => PracticeUtil::getName('scoreboard.arena-ffa.arena'),
			'online' => PracticeUtil::getName('scoreboard.spawn.online-players'),
			'in-fights' => PracticeUtil::getName('scoreboard.spawn.in-fights'),
			'in-queues' => PracticeUtil::getName('scoreboard.spawn.in-queues'),
			'opponent' => PracticeUtil::getName('scoreboard.duels.opponent'),
			'duration' => PracticeUtil::getName('scoreboard.duels.duration')
		];
	}
}