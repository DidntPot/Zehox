<?php

declare(strict_types=1);

namespace practice\commands\parameters;

use JetBrains\PhpStorm\Pure;

class BaseParameter implements Parameter
{
    /** @var string */
    private string $permission;
    /** @var string */
    private string $name;
    /** @var string */
    private string $description;

    /**
     * BaseParameter constructor.
     * @param string $name
     * @param string $basePermission
     * @param string $desc
     * @param bool $hasPerm
     */
    #[Pure] public function __construct(string $name, string $basePermission, string $desc, bool $hasPerm = true)
    {
        $this->name = $name;
        $this->description = $desc;
        if ($hasPerm and $basePermission !== Parameter::NO_PERMISSION) {
            $this->permission = "$basePermission." . $this->getName();
        } else {
            $this->permission = Parameter::NO_PERMISSION;
        }
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return bool
     */
    public function hasPermission(): bool
    {
        return $this->permission != null and $this->permission !== Parameter::NO_PERMISSION;
    }

    /**
     * @return string
     */
    public function getPermission(): string
    {
        return $this->permission;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }
}