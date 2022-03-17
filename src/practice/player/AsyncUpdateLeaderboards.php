<?php

declare(strict_types=1);

namespace practice\player;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use practice\PracticeCore;

class AsyncUpdateLeaderboards extends AsyncTask
{
    /* @var string */
    private string $playerFolderPath;
    /** @var bool */
    private bool $mysqlEnabled;
    /** @var array */
    private array $kits;

    /**
     * @param string $playerFolderPath
     * @param bool $mysqlEnabled
     * @param array $kits
     */
    public function __construct(string $playerFolderPath, bool $mysqlEnabled, array $kits)
    {
        $this->playerFolderPath = $playerFolderPath;
        $this->mysqlEnabled = $mysqlEnabled;
        $this->kits = $kits;
    }

    /**
     * @return void
     */
    public function onRun(): void
    {
        foreach ($this->kits as $name) {
            $leaderboard = $this->getLeaderboardsFrom($name);
            if (is_bool($leaderboard)) {
                $this->setResult($leaderboard);
                return;
            }

            $result[$name] = $leaderboard;
        }

        $global = $this->getLeaderboardsFrom();

        $result['global'] = $global;

        $this->setResult($result);
    }

    /**
     * @param string $queue
     * @return string[]|bool
     */
    public function getLeaderboardsFrom(string $queue = 'global'): array|bool
    {
        $result = [];

        $format = "\n" . TextFormat::GRAY . '%spot%. ' . TextFormat::AQUA . '%player% ' . TextFormat::WHITE . '(%elo%)';

        if ($this->mysqlEnabled === false) {

            $sortedElo = $this->listEloForAll($queue);

            $playerNames = array_keys($sortedElo);

            $size = count($sortedElo) - 1;

            $subtracted = ($size > 10) ? 9 : $size;

            $len = $size - $subtracted;

            for ($i = $size; $i >= $len; $i--) {
                $place = $size - $i;
                $name = strval($playerNames[$i]);
                $elo = intval($sortedElo[$name]);
                $string = str_replace('%spot%', "" . $place + 1, str_replace('%player%', $name, str_replace('%elo%', "$elo", $format)));
                $result[] = $string;
            }

            $size = count($result);

            if ($size > 10) {
                for ($i = $size; $i > 9; $i--) {
                    if (isset($result[$i]))
                        unset($result[$i]);
                }
            }

        } else {
            $result = false;
        }

        return $result;
    }

    /**
     * @param string $queue
     * @return array
     */
    private function listEloForAll(string $queue): array
    {
        $player_array = [];

        if (is_dir($this->playerFolderPath)) {
            $files = scandir($this->playerFolderPath);
            foreach ($files as $file) {
                if (str_contains($file, '.yml')) {
                    $name = strval(str_replace('.yml', '', $file));

                    $path = $this->playerFolderPath . "/$name.yml";

                    $stats = yaml_parse_file($path)['stats'];

                    $elo = $stats['elo'];

                    $resElo = 0;

                    if ($queue === 'global') {
                        $total = 0;
                        $count = count($elo);
                        $keys = array_keys($elo);
                        foreach ($keys as $q) $total += intval($elo[$q]);

                        $resElo = ($count !== 0) ? intval($total / $count) : 1000;
                    } else {
                        if (isset($elo[$queue])) $resElo = intval($elo[$queue]);
                    }

                    $player_array[$name] = $resElo;
                }
            }
        }

        asort($player_array);
        return $player_array;
    }

    /**
     * @return void
     */
    public function onCompletion(): void
    {
        $server = Server::getInstance();
        $plugin = $server->getPluginManager()->getPlugin('Practice');

        if ($plugin !== null and $plugin instanceof PracticeCore) {
            $result = $this->getResult();

            if (is_bool($result)) {
                PracticeCore::getPlayerHandler()->updateLeaderboards();
            } else PracticeCore::getPlayerHandler()->setLeaderboards($result);
        }
    }
}