<?php

declare(strict_types=1);

namespace practice\commands\basic;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\CommandException;
use pocketmine\player\Player;
use practice\PracticeCore;
use practice\PracticeUtil;

class AcceptCommand extends Command
{
    public function __construct()
    {
        parent::__construct("accept", "Allows player to accept a duel request.", "Usage: /accept [target:player]");
        self::setPermission("practice.permission.accept");
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
            if (PracticeUtil::canExecAcceptCommand($sender, $this->getPermission())) {
                $count = count($args);
                if ($count === 1) {
                    if (PracticeUtil::canAcceptPlayer($sender, $args[0]))
                        PracticeCore::get1vs1Handler()->acceptRequest($sender, $args[0]);
                } else $msg = $this->getUsage();
            }
        } else $msg = PracticeUtil::getMessage("console-usage-command");

        if (!is_null($msg)) $sender->sendMessage($msg);

        return true;
    }
}