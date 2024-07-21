<?php

namespace practice\commands\basic;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\utils\CommandException;
use pocketmine\player\Player;
use practice\duels\groups\Request;
use practice\game\FormUtil;
use practice\game\inventory\InventoryUtil;
use practice\PracticeCore;
use practice\PracticeUtil;

class DuelCommand extends Command{
	public function __construct(){
		parent::__construct("duel", "Command to send an unranked duel request.", "Usage: /duel [target:player]", []);
		self::setPermission("practice.permission.duel");
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

		if($sender instanceof Player){
			if(PracticeUtil::canExecDuelCommand($sender, $this->getPermissions()[0], true)){
				$p = PracticeCore::getPlayerHandler()->getPlayer($sender);
				$count = count($args);
				if($count === 1){
					$requested = $args[0];
					if(Request::canSend($p, $requested)){
						if(PracticeUtil::isItemFormsEnabled()){
							$form = FormUtil::getDuelsForm();
							$p->sendForm($form);
						}else InventoryUtil::sendDuelInv($p->getPlayer());

						PracticeCore::get1vs1Handler()->loadRequest($p->getPlayerName(), $requested);
					}
				}else{
					$msg = $this->getUsage();
				}
			}
		}else{
			$msg = PracticeUtil::getMessage("console-usage-command");
		}

		if(!is_null($msg)) $sender->sendMessage($msg);
		return true;
	}
}