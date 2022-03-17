<?php

declare(strict_types=1);

namespace practice\arenas;

use pocketmine\world\Position;
use practice\kits\Kit;
use practice\PracticeCore;
use practice\PracticeUtil;

class FFAArena extends PracticeArena
{
    /**
     * @param string $name
     * @param bool $canBuild
     * @param Position $center
     * @param $kits
     */
    public function __construct(string $name, bool $canBuild, Position $center, $kits = null)
    {
        parent::__construct($name, self::FFA_ARENA, $canBuild, $center);

        if (!is_null($kits)) {
            if (is_string($kits)) {
                if ($kits !== Kit::NO_KIT) $this->kits = [$kits];
            } elseif ($kits instanceof Kit) {
                $this->kits = [$kits->getName()];
            }
        }
    }

    /**
     * @return array
     */
    public function toMap(): array
    {
        $result = [];

        $result["build"] = $this->canBuild();
        $result["spawn"] = PracticeUtil::getPositionToMap($this->getSpawnPosition());
        $result["level"] = $this->world->getFolderName();

        $kit = null;

        $size = count($this->kits);

        if ($size > 0) {
            $k = $this->kits[0];
            if (is_string($k)) {
                if (PracticeCore::getKitHandler()->isKit($k)) {
                    $kit = $k;
                } else {
                    $kit = Kit::NO_KIT;
                }
            } elseif ($k instanceof Kit) {
                $kit = $k->getName();
            }
        } else {
            $kit = Kit::NO_KIT;
        }

        if (!is_null($kit)) {
            $result["kit"] = $kit;
        }

        return $result;
    }
}