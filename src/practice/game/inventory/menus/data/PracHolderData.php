<?php

namespace practice\game\inventory\menus\data;


use pocketmine\math\Vector3;

class PracHolderData
{
    /** @var Vector3 */
    private Vector3 $position;
    /** @var string */
    private string $customName;

    /**
     * @param Vector3 $position
     * @param string $name
     */
    public function __construct(Vector3 $position, string $name)
    {
        $this->position = $position;
        $this->customName = $name;
    }

    /**
     * @return Vector3
     */
    public function getPos(): Vector3
    {
        return $this->position;
    }

    /**
     * @return string
     */
    public function getCustomName(): string
    {
        return $this->customName;
    }
}