<?php

declare(strict_types=1);

namespace practice\player\disguise;

use JetBrains\PhpStorm\Pure;
use pocketmine\entity\Skin;

class DisguiseInfo{
	/** @var string */
	private string $name;
	/* @var Skin|null */
	private ?Skin $skin;

	/**
	 * @param string    $name
	 * @param Skin|null $skin
	 */
	#[Pure] public function __construct(string $name, Skin $skin = null){
		if(!is_null($skin)) $this->skin = $skin;
		else $this->skin = $this->randomSkin();
		$this->name = $name;
	}

	/**
	 * @return Skin|null
	 */
	private function randomSkin() : ?Skin{
		/*$result = null;
		$size = count(Server::getInstance()->getOnlinePlayers());
		if ($size > 2) {
			foreach (Server::getInstance()->getOnlinePlayers() as $player) {
				$skin = $player->getSkin();
			}
		}*/
		return null;
	}

	/**
	 * @return string
	 */
	public function getName() : string{
		return $this->name;
	}

	/**
	 * @return Skin|null
	 */
	public function getSkin() : ?Skin{
		return $this->skin;
	}
}