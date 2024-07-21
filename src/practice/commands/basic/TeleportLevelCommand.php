<?php

namespace practice\commands\basic;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\CommandException;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use practice\PracticeUtil;

class TeleportLevelCommand extends Command{
	public function __construct(){
		parent::__construct("tplevel", "Teleports player to a level.", "Usage: /tplevel <level>", []);
		parent::setPermission("practice.permission.tplevel");
	}

	/**
	 * @param CommandSender $sender
	 * @param string        $commandLabel
	 * @param string[]      $args
	 *
	 * @return bool
	 * @throws CommandException
	 */
	public function execute(CommandSender $sender, string $commandLabel, array $args) : bool{
		$msg = null;

		if(PracticeUtil::canExecBasicCommand($sender, false)){
			if(PracticeUtil::testPermission($sender, $this->getPermissions()[0])){
				$size = count($args);
				if($size === 1){
					$lvlName = $args[0];
					$level = Server::getInstance()->getWorldManager()->getWorldByName($lvlName);
					if(!is_null($level)){
						$p = Server::getInstance()->getPlayerExact($sender->getName());
						$p->teleport($level->getSpawnLocation());
						$lvlName = $level->getDisplayName();
						$msg = "Successfully teleported to '$lvlName'!";
					}else $msg = TextFormat::RED . "The level '$lvlName' does not exist!";
				}else $msg = $this->getUsage();
			}
		}

		if(!is_null($msg)) $sender->sendMessage($msg);
		return true;
	}
}