<?php

declare(strict_types=1);

namespace practice\manager\tasks;

use mysqli;
use pocketmine\scheduler\AsyncTask;
use practice\manager\MysqlManager;
use practice\PracticeCore;

class AddToDatabaseTask extends AsyncTask
{
    /** @var string */
    private string $player;
    /** @var MysqlManager */
    private MysqlManager $sql;

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
     * @param string $player
     */
    public function __construct(string $player)
    {
        $this->player = $player;
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
    public function onRun(): void
    {
        $sql = new mysqli($this->host, $this->username, $this->pass, $this->database, $this->port);
        $this->sql->addPlayerToDatabase($this->player, $sql);
    }

    /**
     * @return void
     */
    public function onCompletion(): void
    {
        parent::onCompletion();
    }
}