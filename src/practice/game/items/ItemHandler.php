<?php

declare(strict_types=1);

namespace practice\game\items;

use JetBrains\PhpStorm\Pure;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\utils\MobHeadType;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\Item;
use pocketmine\item\ItemTypeIds;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat;
use practice\arenas\FFAArena;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;

class ItemHandler{
	/* @var PracticeItem[] */
	private array $itemList;

	/* @var int */
	private int $hubItemsCount;

	/* @var int */
	private int $duelItemsCount;

	/* @var int */
	private int $ffaItemsCount;

	/* @var int */
	private int $leaderboardItemsCount;

	/* @var int */
	private int $partyItemsCount;

	/* @var ItemTextures */
	private ItemTextures $textures;

	/** @var string[] */
	private array $potions;
	/** @var string[] */
	private array $buckets;

	/**
	 * @param PracticeCore $core
	 */
	public function __construct(PracticeCore $core){
		$this->itemList = [];
		$this->textures = new ItemTextures($core);

		$this->potions = [
			'Water Bottle', 'Water Bottle', 'Water Bottle', 'Water Bottle', 'Water Bottle',
			'Potion of Night Vision', 'Potion of Night Vision', 'Potion of Invisibility',
			'Potion of Invisibility', 'Potion of Leaping', 'Potion of Leaping', 'Potion of Leaping',
			'Potion of Fire Resistance', 'Potion of Fire Resistance', 'Potion of Swiftness', 'Potion of Swiftness',
			'Potion of Swiftness', 'Potion of Slowness', 'Potion of Slowness', 'Potion of Water Breathing',
			'Potion of Water Breathing', 'Potion of Healing', 'Potion of Healing', 'Potion of Harming',
			'Potion of Harming', 'Potion of Poison', 'Potion of Poison', 'Potion of Regeneration', 'Potion of Regeneration',
			'Potion of Regeneration', 'Potion of Strength', 'Potion of Strength', 'Potion of Strength', 'Potion of Weakness',
			'Potion of Weakness', 'Potion of Decay'
		];

		$this->buckets = [
			8 => 'Water Bucket',
			9 => 'Water Bucket',
			10 => 'Lava Bucket',
			11 => 'Lava Bucket'
		];

		$this->init();
	}

	/**
	 * @return void
	 */
	private function init() : void{
		$this->initHubItems();
		$this->initDuelItems();
		$this->initFFAItems();
		$this->initLeaderboardItems();
		$this->initPartyItems();
		$this->initMiscItems();
	}

	/**
	 * @return void
	 */
	private function initHubItems() : void{
		$unranked = new PracticeItem('hub.unranked-duels', 0, VanillaItems::IRON_SWORD()->setCustomName(PracticeUtil::getName('unranked-duels')), 'Iron Sword');
		$ranked = new PracticeItem('hub.ranked-duels', 1, VanillaItems::DIAMOND_SWORD()->setCustomName(PracticeUtil::getName('ranked-duels')), 'Diamond Sword');
		$ffa = new PracticeItem('hub.ffa', 2, VanillaItems::IRON_AXE()->setCustomName(PracticeUtil::getName('play-ffa')), 'Iron Axe');
		$leaderboard = new PracticeItem('hub.leaderboard', 4, VanillaBlocks::MOB_HEAD()->setMobHeadType(MobHeadType::SKELETON)->asItem()->setCustomName(TextFormat::BLUE . '» ' . TextFormat::GREEN . 'Leaderboards ' . TextFormat::BLUE . '«'), 'Steve Head');
		$settings = new PracticeItem('hub.settings', 7, VanillaItems::CLOCK()->setCustomName(TextFormat::BLUE . '» ' . TextFormat::GOLD . 'Your Settings ' . TextFormat::BLUE . '«'), 'Clock');
		$inv = new PracticeItem('hub.duel-inv', 8, VanillaBlocks::CHEST()->asItem()->setCustomName(PracticeUtil::getName('duel-inventory')), 'Chest');

		$this->itemList = [$unranked, $ranked, $ffa, $leaderboard, $settings, $inv];

		$this->hubItemsCount = 6;
	}

	/**
	 * @return void
	 */
	private function initDuelItems() : void{
		$duelKits = PracticeCore::getKitHandler()->getDuelKits();
		$items = [];
		foreach($duelKits as $kit){
			$name = $kit->getName();
			if($kit->hasRepItem()) $items['duels.' . $name] = $kit->getRepItem();
		}

		$count = 0;

		$keys = array_keys($items);

		foreach($keys as $localName){

			$i = $items[$localName];

			if($i instanceof Item)
				$this->itemList[] = new PracticeItem(strval($localName), $count, $i, $this->getTextureOf($i));

			$count++;
		}

		$this->duelItemsCount = $count;
	}

	/**
	 * @param Item $item
	 *
	 * @return string
	 */
	private function getTextureOf(Item $item) : string{
		$i = clone $item;

		$name = $i->getVanillaName();

		if($i->getTypeId() === ItemTypeIds::POTION){
			$name = $this->potions[0];
		}elseif($i->getTypeId() === ItemTypeIds::SPLASH_POTION){
			$name = 'Splash ' . $this->potions[0];
		}elseif($i->getTypeId() === ItemTypeIds::BUCKET){
			if(isset($this->buckets[0]))
				$name = $this->buckets[0];
		}

		return $this->textures->getTexture($name);
	}

	/**
	 * @return void
	 */
	private function initFFAItems() : void{
		$arenas = PracticeCore::getKitHandler()->getFFAArenasWKits();

		$result = [];

		foreach($arenas as $arena){

			if($arena instanceof FFAArena){

				$kit = $arena->getFirstKit();

				if($kit->hasRepItem()){

					$arenaName = $arena->getName();

					$name = PracticeUtil::getName('ffa-name');

					if(PracticeUtil::str_contains(' FFA', $arenaName) and PracticeUtil::str_contains(' FFA', $name))
						$name = PracticeUtil::str_replace($name, [' FFA' => '']);

					$name = PracticeUtil::str_replace($name, ['%kit-name%' => $arenaName]);

					$item = clone $kit->getRepItem();

					$result['ffa.' . $arena->getLocalizedName()] = $item->setCustomName($name);
				}
			}
		}

		$count = 0;

		$keys = array_keys($result);

		foreach($keys as $key){

			$item = $result[$key];

			if($item instanceof Item)
				$this->itemList[] = new PracticeItem(strval($key), $count, $item, $this->getTextureOf($item));

			$count++;
		}

		$this->ffaItemsCount = $count;
	}

	/**
	 * @return void
	 */
	private function initLeaderboardItems() : void{
		$duelKits = PracticeCore::getKitHandler()->getDuelKits();

		$items = [];

		foreach($duelKits as $kit){
			$name = $kit->getName();
			if($kit->hasRepItem()) $items['leaderboard.' . $name] = $kit->getRepItem();
		}

		$count = 0;

		$keys = array_keys($items);

		foreach($keys as $localName){

			$i = $items[$localName];

			if($i instanceof Item)
				$this->itemList[] = new PracticeItem(strval($localName), $count, $i, $this->getTextureOf($i));

			$count++;
		}

		$globalItem = VanillaItems::COMPASS()->setCustomName(TextFormat::RED . 'Global');

		$var = 'leaderboard.global';

		$global = new PracticeItem($var, $count, $globalItem, $this->getTextureOf($globalItem));

		$this->itemList[] = $global;

		$this->leaderboardItemsCount = $count + 2;
	}

	/**
	 * @return void
	 */
	private function initPartyItems() : void{
		$settings = new PracticeItem('party.leader.settings', 0, VanillaItems::COMPASS()->setCustomName(TextFormat::BOLD . TextFormat::BLUE . '» ' . TextFormat::GREEN . 'Party ' . TextFormat::GRAY . 'Settings ' . TextFormat::BLUE . '«'), $this->getTextureOf(VanillaItems::COMPASS()));
		$match = new PracticeItem('party.leader.match', 1, VanillaItems::IRON_SWORD()->setCustomName(TextFormat::BOLD . TextFormat::BLUE . '» ' . TextFormat::AQUA . 'Start a Match' . TextFormat::BLUE . ' «'), $this->getTextureOf(VanillaItems::IRON_SWORD()));
		$queue = new PracticeItem('party.leader.queue', 2, VanillaItems::GOLDEN_SWORD()->setCustomName(TextFormat::BOLD . TextFormat::BLUE . '» ' . TextFormat::GOLD . 'Duel Other Parties ' . TextFormat::BLUE . '«'), $this->getTextureOf(VanillaItems::GOLDEN_SWORD()));

		$leaveParty = new PracticeItem('party.general.leave', 8, VanillaItems::REDSTONE_DUST()->setCustomName(TextFormat::GRAY . '» ' . TextFormat::RED . 'Leave Party ' . TextFormat::GRAY . '«'), $this->getTextureOf(VanillaItems::REDSTONE_DUST()));

		$this->itemList = array_merge($this->itemList, [$settings, $queue, $match, $leaveParty]);

		$this->partyItemsCount = 4;
	}

	/**
	 * @return void
	 */
	private function initMiscItems() : void{
		$exit_queue = new PracticeItem('exit.queue', 8, VanillaItems::REDSTONE_DUST()->setCustomName(PracticeUtil::getName('leave-queue')), $this->getTextureOf(VanillaItems::REDSTONE_DUST()));
		$exit_spec = new PracticeItem('exit.spectator', 8, VanillaItems::DYE()->setColor(DyeColor::GREEN)->setCustomName(PracticeUtil::getName('spec-hub')), $this->getTextureOf(VanillaItems::DYE()->setColor(DyeColor::GREEN)), false);
		$exit_inv = new PracticeItem('exit.inventory', 8, VanillaItems::DYE()->setColor(DyeColor::GREEN)->setCustomName(TextFormat::RED . 'Exit'), $this->getTextureOf((VanillaItems::DYE()->setColor(DyeColor::GREEN))));

		array_push($this->itemList, $exit_queue, $exit_spec, $exit_inv);
	}

	/**
	 * @return void
	 */
	public function reload() : void{
		$this->itemList = [];
		$this->init();
	}

	/**
	 * @param      $player
	 * @param bool $clear
	 *
	 * @return void
	 */
	public function spawnHubItems($player, bool $clear = false) : void{
		$practicePlayer = null;

		if($player instanceof PracticePlayer)
			$practicePlayer = $player;
		else if(PracticeCore::getPlayerHandler()->isPlayerOnline($player))
			$practicePlayer = PracticeCore::getPlayerHandler()->getPlayer($player);

		if($practicePlayer !== null and $practicePlayer->isOnline()){

			$p = $practicePlayer->getPlayer();

			$inventory = $p->getInventory();

			if($clear === true){
				$inventory->clearAll();
				$p->getArmorInventory()->clearAll();
			}

			for($i = 0; $i < $this->hubItemsCount; $i++){

				if(isset($this->itemList[$i])){

					$practiceItem = $this->itemList[$i];

					$localName = $practiceItem->getLocalizedName();

					if(PracticeUtil::str_contains('hub.', $localName)){

						$item = $practiceItem->getItem();
						$slot = $practiceItem->getSlot();

						$exec = true;

						if(!$practicePlayer->hasInfoOfLastDuel())
							$exec = $practiceItem->getLocalizedName() !== 'hub.duel-inv';

						if($exec === true) $inventory->setItem($slot, $item);
					}
				}
			}
		}
	}

	/**
	 * @param $player
	 *
	 * @return void
	 */
	public function spawnQueueItems($player) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){

			$p = PracticeCore::getPlayerHandler()->getPlayer($player);

			$inv = $p->getPlayer()->getInventory();

			$p->getPlayer()->getArmorInventory()->clearAll();

			$inv->clearAll();

			$item = $this->getLeaveQueueItem();

			if($this->isAPracticeItem($item)){

				$i = $item->getItem();

				$slot = $item->getSlot();

				$inv->setItem($slot, $i);
			}
		}
	}

	/**
	 * @return PracticeItem|null
	 */
	#[Pure] public function getLeaveQueueItem() : ?PracticeItem{
		return $this->getFromLocalName('exit.queue');
	}

	/**
	 * @param string $name
	 *
	 * @return PracticeItem|null
	 */
	#[Pure] public function getFromLocalName(string $name) : ?PracticeItem{
		foreach($this->itemList as $item){
			if($item instanceof PracticeItem){
				$localName = $item->getLocalizedName();
				if($localName === $name){
					return $item;
				}
			}
		}
		return null;
	}

	/**
	 * @param $item
	 *
	 * @return bool
	 */
	private function isAPracticeItem($item) : bool{
		return !is_null($item) and $item instanceof PracticeItem;
	}

	/**
	 * @param $player
	 *
	 * @return void
	 */
	public function spawnSpecItems($player) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($player)){

			$p = PracticeCore::getPlayerHandler()->getPlayer($player);

			$inv = $p->getPlayer()->getInventory();

			$inv->clearAll();

			$p->getPlayer()->getArmorInventory()->clearAll();

			$item = $this->getExitSpectatorItem();

			if($this->isAPracticeItem($item)){

				$i = $item->getItem();

				$slot = $item->getSlot();

				$inv->setItem($slot, $i);
			}
		}
	}

	/**
	 * @return PracticeItem|null
	 */
	#[Pure] public function getExitSpectatorItem() : ?PracticeItem{
		return $this->getFromLocalName('exit.spectator');
	}

	/**
	 * @param PracticePlayer $player
	 * @param PracticeItem   $item
	 *
	 * @return bool
	 */
	public function canUseItem(PracticePlayer $player, PracticeItem $item) : bool{
		$result = true;
		if(!is_null($player) and $player->isOnline()){
			$p = $player->getPlayer();
			$level = $p->getWorld();
			if($item->canOnlyUseInLobby()){
				if(!PracticeUtil::areWorldEqual($level, PracticeUtil::getDefaultWorld())){
					$result = false;
				}
			}
		}else{
			$result = false;
		}
		return $result;
	}

	/**
	 * @param Item $item
	 *
	 * @return PracticeItem|null
	 */
	public function getPracticeItem(Item $item) : ?PracticeItem{
		$result = null;
		if($this->isPracticeItem($item)){
			$practiceItem = $this->itemList[$this->indexOf($item)];
			if($practiceItem instanceof PracticeItem){
				$result = $practiceItem;
			}
		}
		return $result;
	}

	/**
	 * @param Item $item
	 *
	 * @return bool
	 */
	public function isPracticeItem(Item $item) : bool{
		return $this->indexOf($item) !== -1;
	}

	/**
	 * @param Item $item
	 *
	 * @return int
	 */
	private function indexOf(Item $item) : int{
		$result = -1;
		$count = 0;
		foreach($this->itemList as $i){
			$practiceItem = $i->getItem();
			if($this->itemsEqual($practiceItem, $item)){
				$result = $count;
				break;
			}
			$count++;
		}
		return $result;
	}

	/**
	 * @param Item $item
	 * @param Item $item1
	 *
	 * @return bool
	 */
	private function itemsEqual(Item $item, Item $item1) : bool{
		return $item->equals($item1, true, false) and $item->getName() === $item1->getName();
	}

	/**
	 * @return PracticeItem|null
	 */
	#[Pure] public function getExitInventoryItem() : ?PracticeItem{
		return $this->getFromLocalName('exit.inventory');
	}

	/**
	 * @return PracticeItem[]
	 */
	#[Pure] public function getDuelItems() : array{
		$result = [];

		$start = $this->hubItemsCount;

		$size = $start + $this->duelItemsCount;

		for($i = $start; $i < $size; $i++){

			if(isset($this->itemList[$i])){

				$item = $this->itemList[$i];

				$localName = $item->getLocalizedName();

				if(PracticeUtil::str_contains('duels.', $localName))

					$result[] = $item;

			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	public function getLeaderboardItems() : array{
		$result = [];

		$size = $this->leaderboardItemsCount;

		$start = $this->hubItemsCount + $this->duelItemsCount;

		$len = $start + $size;

		$leaderboards = PracticeCore::getPlayerHandler()->getCurrentLeaderboards();

		for($i = $start; $i <= $len; $i++){

			if(isset($this->itemList[$i])){

				$practiceItem = $this->itemList[$i];

				$localName = $practiceItem->getLocalizedName();

				if(PracticeUtil::str_contains('leaderboard.', $localName)){

					$name = $practiceItem->getName();

					$uncoloredName = PracticeUtil::getUncoloredString($name);

					if(PracticeUtil::equals_string($uncoloredName, 'Global', 'global', 'GLOBAL', 'global '))
						$uncoloredName = 'global';

					$leaderboard = $leaderboards[$uncoloredName] ?? [];

					$item = clone $practiceItem->getItem();

					$item = $item->setLore($leaderboard);

					$practiceItem = $practiceItem->setItem($item);

					$result[] = $practiceItem;
				}
			}
		}

		return $result;
	}

	/**
	 * @return array
	 */
	#[Pure] public function getFFAItems() : array{
		$result = [];

		$start = $this->hubItemsCount + $this->duelItemsCount;

		$size = $start + $this->hubItemsCount;

		for($i = $start; $i < $size; $i++){

			if(isset($this->itemList[$i])){

				$item = $this->itemList[$i];

				$localName = $item->getLocalizedName();

				if(PracticeUtil::str_contains('ffa.', $localName))
					$result[] = $item;

			}
		}
		return $result;
	}

	/**
	 * @param Player $player
	 * @param int    $members
	 * @param bool   $leader
	 * @param bool   $clearInv
	 *
	 * @return void
	 */
	public function spawnPartyItems(Player $player, int $members, bool $leader = false, bool $clearInv = true) : void{

		$start = $this->hubItemsCount + $this->duelItemsCount + $this->leaderboardItemsCount;

		$size = $this->partyItemsCount + $start;

		$numPlayers = $members;

		$inv = $player->getInventory();
		$armorInv = $player->getArmorInventory();

		if($clearInv === true){
			$inv->clearAll();
			$armorInv->clearAll();
		}

		for($i = $start; $i < $size; $i++){

			if(isset($this->itemList[$i])){

				$item = $this->itemList[$i];

				$localName = $item->getLocalizedName();

				$slot = $item->getSlot();

				if(PracticeUtil::str_contains($localName, 'party.')){

					$exec = !(($leader === false and PracticeUtil::str_contains('leader.', $localName)));

					if($exec === true){

						$i = clone $item->getItem();

						if(PracticeUtil::str_contains('.match', $localName)){

							if($numPlayers < 3) continue;

							$n = $numPlayers / 2;

							$replaced = $n . 'vs' . $n;

							$loreStr = TextFormat::RED . $replaced;

							$i = $i->setLore([$loreStr]);

						}elseif(PracticeUtil::str_contains('.queue', $localName)){

							if($numPlayers !== 2) continue;
						}

						$inv->setItem($slot, $i);
					}
				}
			}
		}
	}
}