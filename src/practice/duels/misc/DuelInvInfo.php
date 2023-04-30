<?php

declare(strict_types=1);

namespace practice\duels\misc;

use JetBrains\PhpStorm\Pure;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\item\ItemIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use practice\PracticeUtil;

class DuelInvInfo{
	/** @var array */
	private array $items;
	/** @var array */
	private array $armor;
	/** @var int */
	private int $health;
	/** @var int */
	private int $hunger;
	/** @var int */
	private int $numHits;
	/** @var string */
	private string $playerName;
	/** @var string */
	private string $queue;
	/** @var int */
	private int $potionCount;
	/** @var int */
	private int $soupCount;

	/**
	 * @param Player $player
	 * @param string $queue
	 * @param int    $numHits
	 */
	public function __construct(Player $player, string $queue, int $numHits){
		$this->queue = $queue;
		$this->items = [];
		$this->armor = [];
		$this->health = intval(round($player->getHealth()));
		$this->hunger = intval(round($player->getHungerManager()->getFood()));
		$this->playerName = $player->getName();

		$this->potionCount = 0;
		$this->soupCount = 0;

		$this->numHits = $numHits;

		$arr = PracticeUtil::inventoryToArray($player, true);

		$itemArr = $arr["items"];
		$armorArr = $arr["armor"];

		$armorKeys = ["helmet" => 0, "chestplate" => 1, "leggings" => 2, "boots" => 3];

		$keys = array_keys($armorArr);

		foreach($keys as $key){

			$index = $armorKeys[$key];
			$val = $armorArr[$key];

			if($val instanceof Item) $this->armor[$index] = $val;
		}

		foreach($itemArr as $item){
			if($item instanceof Item){
				$this->items[] = $item;
				if($this->displayPots() === true and $item->getId() === ItemIds::SPLASH_POTION) $this->potionCount++;
				elseif($this->displaySoup() === true and $item->getId() === ItemIds::MUSHROOM_STEW) $this->soupCount++;
			}
		}
	}

	/**
	 * @return bool
	 */
	#[Pure] private function displayPots() : bool{
		return PracticeUtil::equals_string($this->queue, "NoDebuff", "nodebuff", "NODEBUFF", "PotPvP", "PotionPvP", "No Debuff", "No-Debuff");
	}

	/**
	 * @return bool
	 */
	#[Pure] private function displaySoup() : bool{
		return PracticeUtil::equals_string($this->queue, "SoupPvP", "Soup", "soup", "SOUP", "Soup-PvP", "Soup PvP");
	}

	/**
	 * @return string
	 */
	public function getPlayerName() : string{
		return $this->playerName;
	}

	/**
	 * @return array
	 */
	public function getArmor() : array{
		return $this->armor;
	}

	/**
	 * @return array
	 */
	public function getItems() : array{
		return $this->items;
	}

	/**
	 * @return Item
	 */
	public function getItem() : Item{
		return VanillaItems::NETHER_STAR()->setCustomName(TextFormat::RED . $this->playerName);
	}

	/**
	 * @return array
	 */
	public function getStatsItems() : array{
		$head = VanillaItems::ZOMBIE_HEAD()->setCustomName(TextFormat::YELLOW . $this->playerName . TextFormat::RESET);
		$healthItem = (new ItemFactory)->get(ItemIds::GLISTERING_MELON, 1, PracticeUtil::getProperCount($this->getHealth()))->setCustomName(TextFormat::RED . "$this->health HP");
		$numHitsItem = (new ItemFactory)->get(ItemIds::PAPER, 0, PracticeUtil::getProperCount($this->getNumHits()))->setCustomName(TextFormat::GOLD . "$this->numHits Hits");
		$hungerItem = (new ItemFactory)->get(ItemIds::STEAK, 0, PracticeUtil::getProperCount($this->getHunger()))->setCustomName(TextFormat::GREEN . "$this->hunger Hunger-Points");
		$numPots = (new ItemFactory)->get(ItemIds::SPLASH_POTION, 21, PracticeUtil::getProperCount($this->potionCount))->setCustomName(TextFormat::AQUA . "$this->potionCount Pots");
		$numSoup = (new ItemFactory)->get(ItemIds::MUSHROOM_STEW, 0, PracticeUtil::getProperCount($this->soupCount))->setCustomName(TextFormat::BLUE . "$this->soupCount Soup");

		$arr = [$head, $healthItem, $hungerItem, $numHitsItem];

		if($this->displayPots()) $arr[] = $numPots;
		elseif($this->displaySoup()) $arr[] = $numSoup;

		return $arr;
	}

	public function getHealth() : int{
		return $this->health;
	}

	public function getNumHits() : int{
		return $this->numHits;
	}

	public function getHunger() : int{
		return $this->hunger;
	}
}