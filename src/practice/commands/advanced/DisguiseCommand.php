<?php

namespace practice\commands\advanced;

use pocketmine\command\CommandSender;
use practice\commands\BaseCommand;
use practice\commands\parameters\BaseParameter;
use practice\commands\parameters\Parameter;

class DisguiseCommand extends BaseCommand
{
    public function __construct()
    {
        parent::__construct("disguise", "The base disguise command.", "/disguise help");
        $parameters = [
            0 => [
                new BaseParameter("help", Parameter::NO_PERMISSION, "Lists all disguise commands.")
            ],
            1 => [
                new BaseParameter("on", $this->getPermission(), "Turns on a disguise for the user.")
            ],
            2 => [
                new BaseParameter("off", $this->getPermission(), "Turns off the user's disguise.")
            ]
        ];
        $this->setParameters($parameters);
    }

    /**
     * @param CommandSender $sender
     * @param string $commandLabel
     * @param array $args
     * @return void
     */
    public function execute(CommandSender $sender, string $commandLabel, array $args): void
    {
        $msg = null;

        if ($this->canExecute($sender, $args)) {
            $name = strval($args[0]);
            switch ($name) {
                case "help":
                    $msg = $this->getFullUsage();
                    break;
                case "on":
                    $this->turnOnDisguise($sender);
                    break;
                case "off":
                    $this->turnOffDisguise($sender);
                    break;
                default:
                    $msg = $this->getFullUsage();
            }
        }

        if (!is_null($msg)) $sender->sendMessage($msg);
    }

    /**
     * @param CommandSender $sender
     * @return void
     */
    private function turnOnDisguise(CommandSender $sender): void
    {
    }

    /**
     * @param CommandSender $sender
     * @return void
     */
    private function turnOffDisguise(CommandSender $sender): void
    {
    }
}