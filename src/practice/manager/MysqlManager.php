<?php

declare(strict_types=1);

namespace practice\manager;

use JetBrains\PhpStorm\Pure;
use mysqli;
use mysqli_result;
use pocketmine\utils\Config;
use practice\game\PracticeTime;
use practice\player\PracticePlayer;
use practice\PracticeCore;
use practice\PracticeUtil;
use function mysqli_connect;

class MysqlManager{
	/** @var string */
	const ORDER_DESC = 'DESC';
	/** @var string */
	private string $dir;
	/* @var mysqli */
	private mysqli $sql;

	/**
	 * @param string $directory
	 */
	public function __construct(string $directory){
		$name = 'mysql.yml';
		$this->dir = $directory . $name;
		if(PracticeUtil::isMysqlEnabled()) $this->initDatabase();
	}

	/**
	 * @return void
	 */
	private function initDatabase() : void{
		$db = $this->getDatabaseName();

		$this->sql = $this->getMysql();

		if(!$this->sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$currentDatabase = mysqli_select_db($this->sql, $db);

		if($currentDatabase === false){

			$queryString = $this->queryCreateDB($db);

			mysqli_query($this->sql, $queryString);
		}

		$showTablesString = $this->queryShowTables() . ' IN ' . $db;

		$query = mysqli_query($this->sql, $showTablesString);

		$tables = mysqli_fetch_array($query);

		$this->initPlayersForDb($this->sql, $tables);
	}

	/**
	 * @return string
	 */
	public function getDatabaseName() : string{
		$config = $this->getConfig();
		return strval($config->get('database-name'));
	}

	/**
	 * @return Config
	 */
	private function getConfig() : Config{
		return new Config($this->dir, Config::YAML);
	}

	/**
	 * @return mysqli
	 */
	private function getMysql() : mysqli{
		$user = $this->getUsername();
		$host = $this->getHost();
		$pass = $this->getPassword();
		$port = $this->getPort();

		return mysqli_connect($host, $user, $pass, '', $port);
	}

	/**
	 * @return string
	 */
	public function getUsername() : string{
		$config = $this->getConfig();
		return strval($config->get('username'));
	}

	/**
	 * @return string
	 */
	public function getHost() : string{
		$config = $this->getConfig();
		return strval($config->get('host'));
	}

	/**
	 * @return string
	 */
	public function getPassword() : string{
		$config = $this->getConfig();
		return strval($config->get('password'));
	}

	/**
	 * @return int
	 */
	public function getPort() : int{
		$config = $this->getConfig();
		return intval($config->get('port'));
	}

	/**
	 * @param string $dbName
	 *
	 * @return string
	 */
	private function queryCreateDB(string $dbName) : string{
		return 'CREATE DATABASE ' . $dbName;
	}

	/**
	 * @return string
	 */
	public function queryShowTables() : string{
		return 'SHOW TABLES';
	}

	/**
	 * @param mysqli $sql
	 * @param        $tables
	 *
	 * @return void
	 */
	private function initPlayersForDb(mysqli $sql, $tables) : void{

		$this->initPlayersTable($sql, $tables);

		$this->initEloColumns($sql);
	}

	/**
	 * @param mysqli $sql
	 * @param        $tables
	 *
	 * @return void
	 */
	private function initPlayersTable(mysqli $sql, $tables) : void{

		$query = false;

		if(is_null($tables)){

			$query = true;

		}elseif(is_array($tables) and (!PracticeUtil::arr_contains_value('bans', $tables) or empty($tables))){

			$query = true;
		}

		if($query === true){

			$queryString = "CREATE TABLE players(username VARCHAR(30), kills SMALLINT, deaths SMALLINT,  PRIMARY KEY (username))";

			mysqli_query($sql, $queryString);
		}
	}

	/**
	 * @param mysqli $sql
	 *
	 * @return void
	 */
	private function initEloColumns(mysqli $sql) : void{

		$kits = PracticeCore::getKitHandler()->getDuelKitNames(true, true);

		$columns = $this->getColumns($sql, false, false);

		$previous = '';
		$queryHalf = '';

		$count = 0;
		$len = count($kits) - 1;

		$lowerCaseArr = [];

		foreach($kits as $kit){

			$kit = PracticeUtil::str_replace(strval($kit), [' ' => '']);
			$after = ($previous === '') ? 'deaths' : $previous;
			$comma = ($count === $len) ? '' : ',';
			$lowerCase = strtolower($kit);

			if(!isset($columns[$lowerCase])){
				$queryHalf .= ' ADD COLUMN ' . $lowerCase . ' SMALLINT DEFAULT 1000 AFTER ' . $after . $comma;
				$previous = $lowerCase;
			}

			$lowerCaseArr[] = $lowerCase;

			$count++;
		}

		$keys = array_keys($columns);

		$difference = array_diff($keys, $lowerCaseArr, ['kills', 'deaths', 'username']);

		$removed = '';

		$kitHandler = PracticeCore::getKitHandler();

		foreach($difference as $val){
			$val = strval($val);
			if(!$kitHandler->isDuelKit($val, true))
				$removed .= '=DROP COLUMN ' . $val . '-';
		}

		$removed = PracticeUtil::str_replace($removed, ['-=' => ', ']);

		$removed = PracticeUtil::str_replace($removed, ['-' => ' ', '=' => ' ']);

		if($queryHalf !== ''){

			$newColumnsQuery = "ALTER TABLE players" . $queryHalf;

			mysqli_query($sql, $newColumnsQuery);
		}

		if($removed !== ''){

			$otherColumnsQuery = "ALTER TABLE players" . $removed;

			mysqli_query($sql, $otherColumnsQuery);
		}
	}

	/**
	 * @param mysqli $sql
	 * @param bool   $justElo
	 * @param bool   $close
	 *
	 * @return array
	 */
	private function getColumns(mysqli $sql, bool $justElo = false, bool $close = true) : array{

		$showColumnsQuery = "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE table_name = 'players'";

		$columnsQuery = mysqli_query($sql, $showColumnsQuery);

		$exportedColumns = $columnsQuery->fetch_all(MYSQLI_ASSOC);

		$columns = [];

		foreach($exportedColumns as $column){

			$name = strval($column['COLUMN_NAME']);

			$exec = !($justElo === true) || ($name !== 'username' and $name !== 'kills' and $name !== 'deaths');

			if($exec === true) $columns[$name] = true;
		}

		return $columns;
	}

	/**
	 * @param string      $player
	 * @param mysqli|null $sql
	 *
	 * @return void
	 */
	public function addPlayerToDatabase(string $player, mysqli $sql = null) : void{

		$sql = ($sql === null) ? $this->getMysql() : $sql;

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$columns = $this->getColumns($sql);

		$keys = array_keys($columns);

		$newArr = [];

		$lowercase = strtolower($player);

		foreach($keys as $key){
			$key = strval($key);
			if($key === 'username')
				$newArr['username'] = $lowercase;
			elseif($key === 'kills')
				$newArr['kills'] = 0;
			elseif($key === 'deaths')
				$newArr['deaths'] = 0;
			else $newArr[$key] = 1000;
		}

		$str = 'SELECT username FROM players WHERE username="' . $lowercase . '"';

		$data = mysqli_query($sql, $str);

		if($data instanceof mysqli_result){

			$arr = mysqli_fetch_array($data);

			if(empty($arr)){

				$dataKeys = '';
				$dataValues = '';
				$keys = array_keys($newArr);
				$count = 0;
				$len = count($keys) - 1;

				foreach($keys as $key){
					$key = strval($key);
					$value = $newArr[$key];
					$comma = ($count === $len) ? '' : ',';
					$dataKeys .= $key . $comma;
					$dataValues .= (($key === 'username') ? "'$value'" : $value) . $comma;
					$count++;
				}

				$str = 'INSERT INTO players(' . $dataKeys . ') VALUES(' . $dataValues . ')';
				mysqli_query($sql, $str);
			}
		}
	}

	/**
	 * @param mysqli $sql
	 * @param string $player
	 * @param string $queue
	 * @param int    $value
	 *
	 * @return void
	 */
	public function setElo(mysqli $sql, string $player, string $queue, int $value) : void{

		$key = strtolower(PracticeUtil::str_replace($queue, [' ' => '']));

		$player = strtolower($player);

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$queryStr = 'UPDATE players SET ' . $key . " = $value WHERE username = '{$player}'";

		mysqli_query($sql, $queryStr);
	}

	/**
	 * @param string $player
	 * @param string $kit
	 *
	 * @return int
	 */
	public function getElo(string $player, string $kit) : int{

		$key = strtolower(PracticeUtil::str_replace($kit, [' ' => '']));
		$sql = $this->sql;

		$player = strtolower($player);

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$queryStr = "SELECT $key FROM players WHERE username='{$player}'";

		$query = mysqli_query($sql, $queryStr);

		$val = [$key => 1000];

		if($query instanceof mysqli_result){

			$val = mysqli_fetch_array($query);

		}

		//mysqli_close($sql);

		return intval($val[$key]);
	}

	/**
	 * @param string $kit
	 *
	 * @return void
	 */
	public function removeEloColumn(string $kit) : void{

		$sql = $this->sql;

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$kitToDb = strtolower(PracticeUtil::str_replace($kit, [' ' => '']));
		$queryString = "ALTER TABLE players DROP COLUMN " . $kitToDb;
		mysqli_query($sql, $queryString);
		//mysqli_close($sql);
	}

	/**
	 * @param string $kit
	 *
	 * @return void
	 */
	public function addEloColumn(string $kit) : void{

		$kitToDb = strtolower(PracticeUtil::str_replace($kit, [' ' => '']));
		$queryString = "ALTER TABLE players ADD " . $kitToDb . " SMALLINT DEFAULT 1000";

		$sql = $this->sql;

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		mysqli_query($sql, $queryString);

		//mysqli_close($sql);
	}

	/**
	 * @param string $player
	 *
	 * @return int
	 */
	public function addKill(string $player) : int{

		$kills = $this->getKills($player) + 1;

		$player = strtolower($player);

		$sql = $this->sql;

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$queryStr = "UPDATE players SET kills = $kills WHERE username = '{$player}'";

		mysqli_query($sql, $queryStr);

		//mysqli_close($sql);

		return $kills;
	}

	/**
	 * @param string $player
	 *
	 * @return int
	 */
	public function getKills(string $player) : int{

		$player = strtolower($player);

		$sql = $this->sql;

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$queryStr = "SELECT kills FROM players WHERE username='{$player}'";

		$query = mysqli_query($sql, $queryStr);

		$val = ['kills' => 0];

		if($query instanceof mysqli_result){

			$val = mysqli_fetch_array($query);

		}

		//mysqli_close($sql);

		return intval($val['kills']);
	}

	/**
	 * @param string $player
	 *
	 * @return int
	 */
	public function addDeath(string $player) : int{

		$deaths = $this->getDeaths($player) + 1;

		$player = strtolower($player);

		$sql = $this->sql;

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$queryStr = "UPDATE players SET deaths = $deaths WHERE username = '{$player}'";

		mysqli_query($sql, $queryStr);

		//mysqli_close($sql);

		return $deaths;
	}

	/**
	 * @param string $player
	 *
	 * @return int
	 */
	public function getDeaths(string $player) : int{

		$player = strtolower($player);

		$sql = $this->sql;

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$queryStr = "SELECT deaths FROM players WHERE username='{$player}'";

		$query = mysqli_query($sql, $queryStr);

		$val = ['deaths' => 0];

		if($query instanceof mysqli_result){

			$val = mysqli_fetch_array($query);

		}

		//mysqli_close($sql);

		return intval($val['deaths']);
	}

	/**
	 * @param string $kit
	 *
	 * @return array
	 */
	public function getLeaderboardsFrom(string $kit = 'global') : array{

		$kitToLower = strtolower(PracticeUtil::str_replace($kit, [' ' => '']));

		$sql = $this->sql;

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$columns = $this->getColumns($sql, true);

		$list = array_keys($columns);

		$result = [];

		$array = [];

		if($kitToLower !== 'global' and isset($columns[$kitToLower])){

			$queryStr = "SELECT username, $kitToLower FROM players ORDER BY $kitToLower";
			$query = mysqli_query($sql, $queryStr);

			if($query instanceof mysqli_result)
				$array = mysqli_fetch_all($query);

		}elseif($kitToLower === 'global'){

			$order = '';
			$count = 0;
			$len = count($list) - 1;

			foreach($list as $k){
				$k = strval($k);
				$comma = $count === $len ? '' : '+';
				$order .= $k . $comma;
				$count++;
			}

			$avg = "($order)/$count";
			$queryStr = "SELECT username, " . $avg . " AS Average FROM players ORDER BY Average ASC";
			$query = mysqli_query($sql, $queryStr);

			if($query instanceof mysqli_result)
				$array = mysqli_fetch_all($query);
		}

		if(!empty($array)){

			$size = count($array) - 1;

			$subtracted = ($size > 10) ? 9 : $size;

			if($size < 0) $size = 0;

			$len = $size - $subtracted;

			for($i = $size; $i >= $len; $i--){
				$arr = $array[$i];
				$name = strval($arr[0]);
				$elo = intval($arr[1]);
				$result[$name] = $elo;
			}
		}

		//mysqli_close($sql);

		return $result;
	}

	/**
	 * @param string $player
	 *
	 * @return array
	 */
	public function getStats(string $player) : array{

		$sql = $this->sql;

		$player = strtolower($player);

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		$columns = $this->getColumns($sql);

		if(isset($columns['username']))
			unset($columns['username']);

		$str = '';

		$keys = array_keys($columns);

		$count = 0;

		$len = count($columns) - 1;

		foreach($keys as $key){
			$key = strval($key);
			$comma = ($count === $len) ? '' : ',';
			$str .= $key . $comma;
			$count++;
		}

		$queryStr = "SELECT $str FROM players WHERE username='{$player}'";

		$query = mysqli_query($sql, $queryStr);

		$result = [];

		if($query !== false){

			$arr = mysqli_fetch_array($query);

			$cols = array_keys($arr);

			foreach($cols as $col){
				if(is_string($col)){
					$value = intval($arr[$col]);
					$result[$col] = $value;
				}
			}
		}

		return $result;
	}

	/**
	 * @param string $banner
	 * @param string $player
	 *
	 * @return void
	 */
	public function banOfflinePlayer(string $banner, string $player) : void{

		$username = strtolower($player);

		$banner = strtolower($banner);

		$playerHandler = PracticeCore::getPlayerHandler();

		$ipHandler = PracticeCore::getIPHandler();

		$ips = $playerHandler->getIps($player);

		$decodedIps = $ipHandler->decodeIPsFromArr($ips);

		$len = count($decodedIps);

		if($len > 0){

			$firstIp = $decodedIps[0];

			$sql = $this->sql;

			if(!$sql)
				die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

			$queryString = 'INSERT INTO bans(username,banner,ip) VALUES (' . $username . ",'{$banner}','{$firstIp}') ON DUPLICATE KEY UPDATE ip='{$firstIp}'";
		}
	}

	/**
	 * @param string            $banner
	 * @param PracticePlayer    $player
	 * @param PracticeTime|null $time
	 *
	 * @return void
	 */
	public function banOnlinePlayer(string $banner, PracticePlayer $player, PracticeTime $time = null) : void{

		$deviceID = $player->getDeviceID();
		$cid = $player->getCID();
		$ip = $player->getPlayer()->getAddress();
		$username = strtolower($player->getPlayerName());
		$banner = strtolower($banner);

		$vars = ['banner' => $banner];

		$timeStr = (!is_null($time)) ? $time->formatToSql() : '';

		if($deviceID !== '')
			$vars['deviceid'] = $deviceID;

		if($cid !== -1)
			$vars['cid'] = $cid;

		if($ip !== '')
			$vars['ip'] = $ip;

		if($username !== '')
			$vars['username'] = $username;

		if($timeStr !== '')
			$vars['endban'] = $timeStr;

		$varStr = '';

		$valStr = '';

		$keys = array_keys($vars);

		$len = count($keys);

		$updateStr = '';

		if($len > 0){
			$len--;
			$count = 0;
			foreach($keys as $key){
				$key = strval($key);
				$value = $vars[$key];
				$comma = ($count === $len) ? '' : ',';
				$varStr .= $key . $comma;
				$valStr .= $value . $comma;
				$updateStr .= $key . '=' . ((is_string($value)) ? "'$value'" : $value) . $comma;
				$count++;
			}
		}

		if($varStr !== '' and $valStr !== ''){

			$sql = $this->sql;

			if(!$sql)
				die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());
		}
	}

	/**
	 * @param string $player
	 *
	 * @return void
	 */
	public function unbanPlayer(string $player) : void{
		$username = strtolower($player);
	}

	/**
	 * @param string $player
	 *
	 * @return bool
	 */
	public function checkIfBanned(string $player) : bool{

		$username = strtolower($player);

		$sql = $this->sql;

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

		return false;
	}

	/**
	 * @return void
	 */
	public function updateTempBans() : void{
		$tempQuery = "SELECT * FROM bans WHERE tempban <> NULL";

		$sql = $this->sql;

		if(!$sql)
			die('[MYSQL] Could not connect (' . mysqli_connect_errno() . ') ' . mysqli_connect_error());

	}

	/**
	 * @param mysqli $sql
	 * @param        $tables
	 *
	 * @return void
	 */
	#[Pure] private function initBansTable(mysqli $sql, $tables) : void{
		$query = false;

		if(is_null($tables)){

			$query = true;

		}elseif(is_array($tables) and (!PracticeUtil::arr_contains_value('bans', $tables) or empty($tables))){

			$query = true;
		}

		if($query === true){
			$queryString = "CREATE TABLE IF NOT EXISTS bans(username VARCHAR(30), banner VARCHAR(30), ip VARCHAR(50), cid INT, deviceid VARCHAR(50), bantime CURRENT_DATE, endban VARCHAR(50),  PRIMARY KEY (username))";
		}
	}
}