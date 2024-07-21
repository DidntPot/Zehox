<?php

declare(strict_types=1);

namespace practice\commands\parameters;

interface Parameter{
	/** @var int */
	const int PARAMTYPE_STRING = 0;
	/** @var int */
	const int PARAMTYPE_INTEGER = 1;
	/** @var int */
	const int PARAMTYPE_TARGET = 2;
	/** @var int */
	const int PARAMTYPE_BOOLEAN = 3;
	/** @var int */
	const int PARAMTYPE_FLOAT = 4;
	/** @var int */
	const int PARAMTYPE_ANY = 5;

	/** @var string */
	const string NO_PERMISSION = "none";

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