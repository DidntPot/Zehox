<?php

declare(strict_types=1);

namespace practice\commands\parameters;

interface Parameter{
	/** @var int */
	const PARAMTYPE_STRING = 0;
	/** @var int */
	const PARAMTYPE_INTEGER = 1;
	/** @var int */
	const PARAMTYPE_TARGET = 2;
	/** @var int */
	const PARAMTYPE_BOOLEAN = 3;
	/** @var int */
	const PARAMTYPE_FLOAT = 4;
	/** @var int */
	const PARAMTYPE_ANY = 5;

	/** @var string */
	const NO_PERMISSION = "none";

	/**
	 * @return string
	 */
	function getName() : string;

	/**
	 * @return bool
	 */
	function hasPermission() : bool;

	/**
	 * @return string
	 */
	function getPermission() : string;
}