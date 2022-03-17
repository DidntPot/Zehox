<?php

declare(strict_types=1);

namespace practice\parties;

use pocketmine\utils\TextFormat;
use practice\PracticeCore;
use practice\PracticeUtil;

class PracticeParty
{
    /** @var int */
    private static int $partyID = 0;
    /** @var array */
    private array $players;
    /** @var string */
    private string $leader;
    /** @var string */
    private string $name;
    /** @var int */
    private int $id;
    /** @var bool */
    private bool $open;

    /**
     * @param string $leader
     * @param string|null $name
     */
    public function __construct(string $leader, string $name = null)
    {
        $this->leader = $leader;
        $this->name = (!is_null($name)) ? $name : 'Party_' . (self::$partyID + 1);
        $this->id = self::$partyID;
        $this->players = [$leader => true];
        $this->open = true;
        self::$partyID++;

        $p = PracticeCore::getPlayerHandler()->getPlayer($leader);
        $player = $p->getPlayer();
        PracticeCore::getItemHandler()->spawnPartyItems($player, count($this->players), true);
    }

    /**
     * @return bool
     */
    public function isPartyOpen(): bool
    {
        return $this->open;
    }

    /**
     * @param bool $open
     * @return $this
     */
    public function setPartyOpen(bool $open): self
    {
        $msg = null;

        if ($this->open === false and $open === true)
            $msg = TextFormat::GREEN . 'Party is now open for anyone to join.';
        elseif ($this->open === true and $open === false) $msg = TextFormat::RED . 'Party is now invite-only.';

        if (!is_null($msg))
            $this->broadcastMsg($msg);

        $this->open = $open;
        return $this;
    }

    /**
     * @param string $msg
     * @param string|null $pl
     * @return void
     */
    private function broadcastMsg(string $msg, string $pl = null): void
    {
        $keys = array_keys($this->players);
        $testIfNull = !is_null($pl);

        foreach ($keys as $key) {
            $exec = !(($testIfNull === true and $pl === $key));
            if ($exec === true and PracticeCore::getPlayerHandler()->isPlayerOnline($key)) {
                $p = PracticeCore::getPlayerHandler()->getPlayer($key);
                $p->sendMessage($msg);
            }
        }
    }

    /**
     * @param string $player
     * @return void
     */
    public function addToParty(string $player): void
    {
        if (PracticeCore::getPlayerHandler()->isPlayerOnline($player)) {
            $p = PracticeCore::getPlayerHandler()->getPlayer($player);
            $pl = $p->getPlayer();
            $this->players[$p->getPlayerName()] = true;
            PracticeCore::getItemHandler()->spawnPartyItems($pl, count($this->players));
            $msg = PracticeUtil::str_replace(PracticeUtil::getMessage('party.general.join-msg'), ["%player%" => $player]);
            $this->broadcastMsg($msg);
        }
    }

    /**
     * @param string $player
     * @param bool $kick
     * @return bool
     */
    public function removeFromParty(string $player, bool $kick = false): bool
    {
        $permanentlyRemove = false;
        if ($this->isInParty($player)) {
            $p = PracticeCore::getPlayerHandler()->getPlayer($player);
            if ($this->isLeaderOfParty($player)) {
                $size = count($this->players);
                if ($size > 1) {
                    $keys = array_keys($this->players);
                    $newLeader = null;
                    foreach ($keys as $key) {
                        if ($key !== $player and PracticeCore::getPlayerHandler()->isPlayerOnline($key)) {
                            $newLeader = strval($key);
                            break;
                        }
                    }
                    if (!is_null($newLeader)) {
                        $this->leader = $newLeader;
                        $newL = PracticeCore::getPlayerHandler()->getPlayer($newLeader);
                        PracticeCore::getItemHandler()->spawnPartyItems($newL->getPlayer(), count($this->players), true);
                        $newLeaderMsg = PracticeUtil::str_replace(PracticeUtil::getMessage('party.general.new-manager'), ['%player%' => 'You']);
                        $newL->sendMessage($newLeaderMsg);
                    } else $permanentlyRemove = true;

                } else $permanentlyRemove = true;
            }

            $leaveMsg = PracticeUtil::getMessage('party.general.leave-msg');
            $kickMsg = PracticeUtil::getMessage('party.kick.message');

            $singularMsg = ($kick === true) ? PracticeUtil::str_replace($kickMsg, ['%player%' => 'You', 'has' => 'have been']) : PracticeUtil::str_replace($leaveMsg, ['%player%' => 'You', 'has' => 'have']);

            $p->sendMessage($singularMsg);

            PracticeUtil::resetPlayer($p->getPlayer());

            unset($this->players[$player]);

            $msg = ($kick === true) ? PracticeUtil::str_replace($kickMsg, ['%player%' => $player]) : PracticeUtil::str_replace($leaveMsg, ['%player%' => $player]);
            $this->broadcastMsg($msg);
        }

        return $permanentlyRemove;
    }

    /**
     * @param string $player
     * @return bool
     */
    public function isInParty(string $player): bool
    {
        return PracticeCore::getPlayerHandler()->isPlayerOnline($player) and isset($this->players[$player]);
    }

    /**
     * @param string $player
     * @return bool
     */
    public function isLeaderOfParty(string $player): bool
    {
        $result = false;
        if ($this->isInParty($player))
            $result = $this->leader === $player;
        return $result;
    }

    /**
     * @return string
     */
    public function getPartyName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getMembers(): array
    {
        return array_keys($this->players);
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }
}