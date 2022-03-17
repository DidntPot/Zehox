<?php

declare(strict_types=1);

namespace practice\commands\parameters;

use JetBrains\PhpStorm\Pure;

class SimpleParameter implements Parameter
{
    /** @var string */
    private string $name;
    /** @var string */
    private string $permission;
    /** @var bool */
    private bool $optional;
    /** @var int */
    private int $paramType;
    /** @var bool */
    private bool $setHasExact;
    /** @var string */
    private string $description;

    /**
     * @param string $theName
     * @param int $type
     * @param string $permission
     * @param string $desc
     */
    public function __construct(string $theName, int $type, string $permission = Parameter::NO_PERMISSION, string $desc = "")
    {
        $this->name = $theName;
        $this->optional = false;
        $this->paramType = $type;
        $this->setHasExact = false;
        $this->description = $desc;
        if ($permission !== Parameter::NO_PERMISSION) {
            $this->permission = "$permission.$theName";
        } else {
            $this->permission = Parameter::NO_PERMISSION;
        }
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return bool
     */
    public function hasDescription(): bool
    {
        return strlen($this->description) > 0;
    }

    /**
     * @param bool $b
     * @return SimpleParameter
     */
    public function setOptional(bool $b): SimpleParameter
    {
        $this->optional = $b;
        return $this;
    }

    /**
     * @param bool $b
     * @return SimpleParameter
     */
    public function setExactValues(bool $b): SimpleParameter
    {
        $this->setHasExact = $b;
        return $this;
    }

    /**
     * @return bool
     */
    public function hasExactValues(): bool
    {
        return $this->setHasExact;
    }

    /**
     * @param string $str
     * @return bool
     */
    #[Pure] public function isExactValue(string $str): bool
    {
        $val = $this->getExactValues();
        $result = false;
        if (is_array($val)) {
            foreach ($val as $key) {
                if (is_string($key) and $str === $key) {
                    $result = true;
                    break;
                }
            }
        } else {
            if (is_string($val)) $result = $str === $val;
        }
        return $result;
    }

    /**
     * @return array|string
     */
    private function getExactValues(): array|string
    {
        $str = $this->name;
        if (strpos($this->name, "|")) {
            $str = explode("|", $this->name);
        }
        return $str;
    }

    /**
     * @return int
     */
    public function getParameterType(): int
    {
        return $this->paramType;
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
        return $this->permission !== Parameter::NO_PERMISSION;
    }

    /**
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->optional;
    }

    /**
     * @return string
     */
    public function getPermission(): string
    {
        return $this->permission;
    }
}