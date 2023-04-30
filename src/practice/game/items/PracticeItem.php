<?php

declare(strict_types=1);

namespace practice\game\items;

use pocketmine\item\Item;

class PracticeItem{
	/** @var string */
	private string $localizedName;
	/** @var int */
	private int $slot;
	/** @var Item */
	private Item $item;
	/** @var string */
	private string $itemName;
	/** @var bool */
	private bool $onlyExecuteInLobby;
	/** @var string */
	private string $texture;

	/**
	 * @param string $name
	 * @param int    $slot
	 * @param Item   $item
	 * @param string $texture
	 * @param bool   $exec
	 */
	public function __construct(string $name, int $slot, Item $item, string $texture, bool $exec = true){
		$this->localizedName = $name;
		$this->slot = $slot;
		$this->item = $item;
		$this->itemName = $item->getName();
		$this->onlyExecuteInLobby = $exec;
		$this->texture = $texture;
	}

	/**
	 * @return string
	 */
	public function getTexture() : string{
		return $this->texture;
	}

	/**
	 * @return bool
	 */
	public function canOnlyUseInLobby() : bool{
		return $this->onlyExecuteInLobby;
	}

	/**
	 * @return Item
	 */
	public function getItem() : Item{
		return $this->item;
	}

	/**
	 * @param Item $item
	 *
	 * @return $this
	 */
	public function setItem(Item $item) : self{
		$this->item = $item;
		$this->itemName = $item->getName();
		return $this;
	}

	/**
	 * @return string
	 */
	public function getName() : string{
		return $this->itemName;
	}

	/**
	 * @return string
	 */
	public function getLocalizedName() : string{
		return $this->localizedName;
	}

	/**
	 * @return int
	 */
	public function getSlot() : int{
		return $this->slot;
	}
}