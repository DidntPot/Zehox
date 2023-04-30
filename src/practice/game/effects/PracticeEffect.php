<?php

declare(strict_types=1);

namespace practice\game\effects;

use JetBrains\PhpStorm\Pure;
use pocketmine\entity\effect\Effect;
use pocketmine\entity\effect\EffectInstance;
use pocketmine\entity\effect\StringToEffectParser;
use pocketmine\player\Player;

class PracticeEffect{
	/** @var int */
	private int $duration;
	/** @var Effect */
	private Effect $effect;
	/** @var int */
	private int $amplifier;

	/**
	 * @param Effect $effect
	 * @param int    $duration
	 * @param int    $amp
	 */
	public function __construct(Effect $effect, int $duration, int $amp){
		$this->effect = $effect;
		$this->duration = $duration;
		$this->amplifier = $amp;
	}

	/**
	 * @param string $line
	 *
	 * @return PracticeEffect
	 */
	public static function getEffectFrom(string $line) : PracticeEffect{
		$split = explode(":", $line);
		$id = intval($split[0]);
		$amp = intval($split[1]);
		$duration = intval($split[2]);
		$effect = StringToEffectParser::getInstance()->get($id);
		return new PracticeEffect($effect, $duration, $amp);
	}

	/**
	 * @return Effect
	 */
	public function getEffect() : Effect{
		return $this->effect;
	}

	/**
	 * @return int
	 */
	public function getDuration() : int{
		return $this->duration;
	}

	/**
	 * @return int
	 */
	public function getAmplifier() : int{
		return $this->amplifier;
	}

	public function applyTo($player) : void{
		if($player instanceof Player){
			$effect = new EffectInstance($this->effect, $this->duration * 20, $this->amplifier);
			$player->getEffects()->add($effect);
		}
	}

	/**
	 * @return string
	 */
	#[Pure] public function toString() : string{
		$id = $this->effect->getName();
		return $id . ":" . $this->amplifier . ":" . $this->duration;
	}
}