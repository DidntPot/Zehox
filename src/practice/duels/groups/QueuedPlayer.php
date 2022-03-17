<?php

declare(strict_types=1);

namespace practice\duels\groups;

use JetBrains\PhpStorm\Pure;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;

class QueuedPlayer
{
    /** @var string */
    private string $playerName;
    /** @var string */
    private string $queue;

    /** @var bool */
    private bool $ranked;
    /** @var bool */
    private bool $peOnly;

    /**
     * @param string $name
     * @param string $queue
     * @param bool $ranked
     * @param bool $peOnly
     */
    public function __construct(string $name, string $queue, bool $ranked = false, bool $peOnly = false)
    {
        $this->playerName = $name;
        $this->queue = $queue;
        $this->ranked = $ranked;
        $this->peOnly = $peOnly;
    }

    /**
     * @return bool
     */
    public function isPEOnly(): bool
    {
        return $this->peOnly;
    }

    /**
     * @return bool
     */
    public function isPlayerOnline(): bool
    {
        return !is_null($this->getPlayer()) and $this->getPlayer()->isOnline();
    }

    /**
     * @return PracticePlayer|null
     */
    public function getPlayer(): ?PracticePlayer
    {
        return PracticeCore::getPlayerHandler()->getPlayer($this->playerName);
    }

    /**
     * @param QueuedPlayer $player
     * @return bool
     */
    #[Pure] public function hasSameQueue(QueuedPlayer $player): bool
    {
        $result = false;
        if ($player->getQueue() === $this->queue) {
            $ranked = $player->isRanked();
            $result = $this->ranked === $ranked;
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }

    /**
     * @return bool
     */
    public function isRanked(): bool
    {
        return $this->ranked;
    }

    /**
     * @param $object
     * @return bool
     */
    #[Pure] public function equals($object): bool
    {
        $result = false;
        if ($object instanceof QueuedPlayer) {
            if ($object->getPlayerName() === $this->playerName) {
                $result = true;
            }
        }
        return $result;
    }

    /**
     * @return string
     */
    public function getPlayerName(): string
    {
        return $this->playerName;
    }

    /**
     * @return string
     */
    public function toString(): string
    {
        $str = PracticeUtil::getName('scoreboard.spawn.thequeue');

        $ranked = ($this->ranked === true) ? 'Ranked' : 'Unranked';
        return PracticeUtil::str_replace($str, ['%ranked%' => $ranked, '%queue%' => $this->queue]);
    }
}