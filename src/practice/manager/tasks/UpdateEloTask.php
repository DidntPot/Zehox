<?php

declare(strict_types=1);

namespace practice\manager\tasks;

use mysqli;
use pocketmine\scheduler\AsyncTask;
use practice\manager\MysqlManager;
use practice\PracticeCore;

class UpdateEloTask extends AsyncTask{
	/** @var MysqlManager */
	private MysqlManager $sql;

	/** @var string */
	private string $winner;
	/** @var int */
	private int $winnerElo;
	/** @var string */
	private string $loser;
	/** @var int */
	private int $loserElo;

	/** @var string */
	private string $queue;

	/** @var string */
	private string $username;
	/** @var string */
	private string $host;
	/** @var string */
	private string $pass;
	/** @var int */
	private int $port;
	/** @var string */
	private string $database;

	/**
	 * @param string $winner
	 * @param string $loser
	 * @param int    $winnerElo
	 * @param int    $loserElo
	 * @param string $queue
	 */
	public function __construct(string $winner, string $loser, int $winnerElo, int $loserElo, string $queue){
		$this->winner = $winner;
		$this->loser = $loser;
		$this->winnerElo = $winnerElo;
		$this->loserElo = $loserElo;

		$this->queue = $queue;

		$this->sql = PracticeCore::getMysqlHandler();
		$this->username = $this->sql->getUsername();
		$this->host = $this->sql->getHost();
		$this->pass = $this->sql->getPassword();
		$this->port = $this->sql->getPort();
		$this->database = $this->sql->getDatabaseName();
	}

	/**
	 * @return void
	 */
	public function onRun() : void{
		$sql = new mysqli($this->host, $this->username, $this->pass, $this->database, $this->port);

		$this->sql->setElo($sql, $this->winner, $this->queue, $this->winnerElo);
		$this->sql->setElo($sql, $this->loser, $this->queue, $this->loserElo);
	}
}