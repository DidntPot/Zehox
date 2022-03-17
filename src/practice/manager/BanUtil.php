<?php

declare(strict_types=1);

namespace practice\manager;

use JetBrains\PhpStorm\Pure;
use practice\game\PracticeTime;
use practice\PracticeCore;

class BanUtil
{
    /**
     * @param string $banner
     * @param string $player
     * @param string $reason
     * @param int $minutes
     * @param int $hours
     * @param int $days
     */
    public static function tempBan(string $banner, string $player, string $reason, int $minutes = -1, int $hours = -1, int $days = -1): void
    {
        $mysql = PracticeCore::getMysqlHandler();

        $time = new PracticeTime();

        if ($minutes > 0)
            $time = $time->add('min', $minutes);

        if ($hours > 0)
            $time = $time->add('hr', $hours);

        if ($days > 0)
            $time = $time->add('day', $days);

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayerOnline($player)) {

            $p = $playerHandler->getPlayer($player);
            $mysql->banOnlinePlayer($banner, $p, $time);

            $ext = (!is_null($time) ? ' until ' . $time->dateForFile(true) : '') . '.';
            $msg = 'Banned for ' . $reason . $ext;

            $p->getPlayer()->kick($msg);

        } else $mysql->banOfflinePlayer($banner, $player);
    }

    /**
     * @param string $banner
     * @param string $player
     * @param string $reason
     */
    public static function permBan(string $banner, string $player, string $reason): void
    {
        $mysql = PracticeCore::getMysqlHandler();

        $playerHandler = PracticeCore::getPlayerHandler();

        if ($playerHandler->isPlayerOnline($player)) {

            $p = $playerHandler->getPlayer($player);
            $mysql->banOnlinePlayer($banner, $p);

            $msg = 'Permanently banned for ' . $reason . '.';

            $p->getPlayer()->kick($msg);

        } else $mysql->banOfflinePlayer($banner, $player);

    }

    /**
     * @param string $player
     * @return void
     */
    #[Pure] public static function unbanPlayer(string $player): void
    {
        $mysql = PracticeCore::getMysqlHandler();
        $mysql->unbanPlayer($player);
    }

    /**
     * @param string $player
     * @return bool
     */
    public static function isPlayerBanned(string $player): bool
    {
        $mysql = PracticeCore::getMysqlHandler();
        return $mysql->checkIfBanned($player);
    }

    /**
     * @return void
     */
    public static function updateTempBans(): void
    {
        $mysql = PracticeCore::getMysqlHandler();
        $mysql->updateTempBans();
    }
}