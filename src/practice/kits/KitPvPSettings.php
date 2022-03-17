<?php

declare(strict_types=1);

namespace practice\kits;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;

class KitPvPSettings
{
    /** @var float */
    private float $knockback;
    /** @var int */
    private int $attack_delay;

    /**
     * @param float $kb
     * @param int $attack_delay
     */
    public function __construct(float $kb = 0.4, int $attack_delay = 10)
    {
        $this->knockback = $kb;
        $this->attack_delay = $attack_delay;
    }

    /**
     * @return int
     */
    public function getAttackDelay(): int
    {
        return $this->attack_delay;
    }

    /**
     * @return array
     */
    #[Pure] #[ArrayShape(["kb" => "float", "attack-delay" => "int"])] public function toMap(): array
    {
        return ["kb" => $this->getKB(), "attack-delay" => $this->attack_delay];
    }

    /**
     * @return float
     */
    public function getKB(): float
    {
        return $this->knockback;
    }
}