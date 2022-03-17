<?php

declare(strict_types=1);

namespace practice\scoreboard;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\player\Player;
use practice\player\PracticePlayer;

class Scoreboard
{
    /** @var int */
    private const SORT_ASCENDING = 0;
    /** @var int */
    private const SORT_DESCENDING = 1;
    /** @var string */
    private const SLOT_SIDEBAR = "sidebar";

    /** @var array */
    private array $lines;

    /** @var string */
    private string $title;

    /** @var Player */
    private Player $player;

    /**
     * @param PracticePlayer $player
     * @param string $title
     */
    public function __construct(PracticePlayer $player, string $title)
    {
        $this->player = $player->getPlayer();
        $this->title = $title;
        $this->lines = [];
        $this->initScoreboard();
    }

    /**
     * @return void
     */
    private function initScoreboard(): void
    {
        $pkt = new SetDisplayObjectivePacket();
        $pkt->objectiveName = $this->player->getName();
        $pkt->displayName = $this->title;
        $pkt->sortOrder = self::SORT_ASCENDING;
        $pkt->displaySlot = self::SLOT_SIDEBAR;
        $pkt->criteriaName = "dummy";

        $this->player->getNetworkSession()->sendDataPacket($pkt);
    }

    /**
     * @return void
     */
    public function clearScoreboard(): void
    {
        $packet = new SetScorePacket();
        $packet->entries = $this->lines;
        $packet->type = SetScorePacket::TYPE_REMOVE;
        $this->player->getNetworkSession()->sendDataPacket($packet);
        $this->lines = [];
    }

    /**
     * @param int $id
     * @param string $line
     * @return void
     */
    public function addLine(int $id, string $line): void
    {
        $entry = new ScorePacketEntry();
        $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;

        if (isset($this->lines[$id])) {
            $pkt = new SetScorePacket();
            $pkt->entries[] = $this->lines[$id];
            $pkt->type = SetScorePacket::TYPE_REMOVE;
            $this->player->getNetworkSession()->sendDataPacket($pkt);
            unset($this->lines[$id]);
        }

        $entry->score = $id;
        $entry->scoreboardId = $id;
        $entry->actorUniqueId = $this->player->getId();
        $entry->objectiveName = $this->player->getName();
        $entry->customName = $line;
        $this->lines[$id] = $entry;

        $pkt = new SetScorePacket();

        $pkt->entries[] = $entry;
        $pkt->type = SetScorePacket::TYPE_CHANGE;
        $this->player->getNetworkSession()->sendDataPacket($pkt);
    }

    /**
     * @param int $id
     * @return void
     */
    public function removeLine(int $id): void
    {
        if (isset($this->lines[$id])) {
            $line = $this->lines[$id];
            $packet = new SetScorePacket();
            $packet->entries[] = $line;
            $packet->type = SetScorePacket::TYPE_REMOVE;
            $this->player->getNetworkSession()->sendDataPacket($packet);
        }

        unset($this->lines[$id]);
    }

    /**
     * @return void
     */
    public function removeScoreboard(): void
    {
        $pkt = new RemoveObjectivePacket();
        $pkt->objectiveName = $this->player->getName();
        $this->player->getNetworkSession()->sendDataPacket($pkt);
    }

    /**
     * @return void
     */
    public function resendScoreboard(): void
    {
        $this->initScoreboard();
    }
}