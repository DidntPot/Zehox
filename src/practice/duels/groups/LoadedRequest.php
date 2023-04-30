<?php

namespace practice\duels\groups;

use JetBrains\PhpStorm\Pure;

class LoadedRequest{
	/** @var string|null */
	private ?string $queue;
	/** @var string */
	private string $player;
	/** @var string */
	private string $requested;

	/**
	 * @param string $player
	 * @param string $requested
	 */
	public function __construct(string $player, string $requested){
		$this->queue = null;
		$this->player = $player;
		$this->requested = $requested;
	}

	/**
	 * @return bool
	 */
	public function hasQueue() : bool{
		return !is_null($this->queue);
	}

	/**
	 * @return string
	 */
	public function getQueue() : string{
		return $this->queue;
	}

	/**
	 * @param string $queue
	 *
	 * @return void
	 */
	public function setQueue(string $queue) : void{
		$this->queue = $queue;
	}

	/**
	 * @param $object
	 *
	 * @return bool
	 */
	#[Pure] public function equals($object) : bool{
		$result = false;
		if($object instanceof LoadedRequest){
			$result = $object->getRequested() === $this->requested and $object->getRequestor() === $this->player;
		}
		return $result;
	}

	/**
	 * @return string
	 */
	public function getRequested() : string{
		return $this->requested;
	}

	/**
	 * @return string
	 */
	public function getRequestor() : string{
		return $this->player;
	}
}