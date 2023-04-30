<?php

namespace practice\duels\misc;

use JetBrains\PhpStorm\Pure;

class DuelPlayerHit{
	/** @var string */
	private string $hitter;
	/** @var int */
	private int $tick;

	/**
	 * @param string $hitter
	 * @param int    $tick
	 */
	public function __construct(string $hitter, int $tick){
		$this->tick = $tick;
		$this->hitter = $hitter;
	}

	/**
	 * @return string
	 */
	public function getHitter() : string{
		return $this->hitter;
	}

	/**
	 * @param $object
	 *
	 * @return bool
	 */
	#[Pure] public function equals($object) : bool{
		$result = false;
		if($object instanceof DuelPlayerHit){
			$result = $this->tick === $object->getTick();
		}
		return $result;
	}

	/**
	 * @return int
	 */
	public function getTick() : int{
		return $this->tick;
	}
}