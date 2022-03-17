<?php

declare(strict_types=1);

namespace practice\duels\groups;

use JetBrains\PhpStorm\Pure;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;

class MatchedGroup
{
    /** @var string */
    private string $playerName;
    /** @var string */
    private string $opponentName;
    /** @var string */
    private string $queue;

    /** @var bool */
    private bool $ranked;

    /**
     * @param $player
     * @param $opponent
     * @param string $queue
     * @param bool $ranked
     */
    #[Pure] public function __construct($player, $opponent, string $queue, bool $ranked = false)
    {
        $pName = PracticeUtil::getPlayerName($player);
        $oName = PracticeUtil::getPlayerName($opponent);

        if (!is_null($pName)) $this->playerName = $pName;
        if (!is_null($oName)) $this->opponentName = $oName;

        $this->queue = $queue;
        $this->ranked = $ranked;
    }

    /**
     * @return bool
     */
    public function isRanked(): bool
    {
        return $this->ranked;
    }

    /**
     * @return bool
     */
    public function isPlayerOnline(): bool
    {
        $p = $this->getPlayer();
        return !is_null($p) and $p->isOnline();
    }

    /**
     * @return PracticePlayer|null
     */
    public function getPlayer(): ?PracticePlayer
    {
        return PracticeCore::getPlayerHandler()->getPlayer($this->playerName);
    }

    /**
     * @return bool
     */
    public function isOpponentOnline(): bool
    {
        $p = $this->getOpponent();
        return !is_null($p) and $p->isOnline();
    }

    /**
     * @return PracticePlayer|null
     */
    public function getOpponent(): ?PracticePlayer
    {
        return PracticeCore::getPlayerHandler()->getPlayer($this->opponentName);
    }

    /**
     * @param $object
     * @return bool
     */
    #[Pure] public function equals($object): bool
    {
        $result = false;
        if ($object instanceof MatchedGroup) {
            if ($this->getPlayerName() === $object->getPlayerName() and $this->getOpponentName() === $object->getOpponentName()) {
                $result = $this->getQueue() === $object->getQueue();
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
    public function getOpponentName(): string
    {
        return $this->opponentName;
    }

    /**
     * @return string
     */
    public function getQueue(): string
    {
        return $this->queue;
    }
}