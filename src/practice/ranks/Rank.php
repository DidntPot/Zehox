<?php

declare(strict_types=1);

namespace practice\ranks;

use JetBrains\PhpStorm\Pure;

class Rank{
	/** @var string */
	private string $localizedName;
	/** @var string */
	private string $name;

	/**
	 * @param string $local
	 * @param string $name
	 */
	public function __construct(string $local, string $name){
		$this->localizedName = $local;
		$this->name = $name;
	}

	/**
	 * @return string
	 */
	public function getName() : string{
		return $this->name;
	}

	/**
	 * @param $object
	 *
	 * @return bool
	 */
	#[Pure] public function equals($object) : bool{
		$result = false;
		if($object instanceof Rank) $result = $object->getLocalizedName() === $this->localizedName;
		return $result;
	}

	/**
	 * @return string
	 */
	public function getLocalizedName() : string{
		return $this->localizedName;
	}
}