<?php

namespace practice\commands\basic;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\CommandException;
use pocketmine\player\Player;
use practice\PracticeCore;
use practice\PracticeUtil;

class SpectateCommand extends Command
{
    public function __construct()
    {
        parent::__construct("spec", "Allows a player to spectate a duel match.", "Usage: /spec [target:player]", []);
        parent::setPermission("practice.permission.spec");
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param string[] $args
     *
     * @return bool
     * @throws CommandException
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args): bool
    {
        $msg = null;

        if ($sender instanceof Player) {
            if (PracticeUtil::canExecSpecCommand($sender, $this->getPermission())) {
                $count = count($args);
                if ($count === 1) {
                    $player = $args[0];
                    if (PracticeCore::getPlayerHandler()->isPlayerOnline($player)) {
                        $p = PracticeCore::getPlayerHandler()->getPlayer($player);
                        if ($p->isInDuel()) {
                            $duel = PracticeCore::getDuelHandler()->getDuel($p->getPlayerName());
                            $duel->addSpectator($sender->getName());
                        } else {
                            $msg = PracticeUtil::getMessage("duels.misc.not-in-duel");
                            $msg = PracticeUtil::str_replace($msg, ["%player%" => $p->getPlayerName()]);
                        }
                    } else {
                        $msg = PracticeUtil::getMessage("not-online");
                        $msg = strval(str_replace("%player-name%", $player, $msg));
                    }
                } else {
                    $msg = $this->getUsage();
                }
            }
        } else $msg = PracticeUtil::getMessage("console-usage-command");

        if (!is_null($msg)) $sender->sendMessage($msg);
        return true;
    }
}