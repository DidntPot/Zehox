<?php

declare(strict_types=1);

namespace practice\player;

use JetBrains\PhpStorm\ArrayShape;
use pocketmine\entity\Entity;
use pocketmine\player\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use practice\manager\tasks\AddToDatabaseTask;
use practice\manager\tasks\CreatePDataTask;
use practice\manager\tasks\UpdateEloTask;
use practice\PracticeCore;
use practice\PracticeUtil;
use practice\ranks\RankHandler;

class PlayerHandler{
	/* @var PracticePlayer[] */
	private array $players;
	/** @var array */
	public array $pendingDeviceData;
	/** @var string */
	private string $playerFolderPath;
	/** @var array */
	private array $leaderboards;
	/** @var Server */
	private Server $server;

	/**
	 * @param PracticeCore $core
	 */
	public function __construct(PracticeCore $core){
		$this->players = [];
		$this->pendingDeviceData = [];
		$this->leaderboards = [];
		$this->initFiles($core);
		$this->server = $core->getServer();
	}

	/**
	 * @param PracticeCore $core
	 *
	 * @return void
	 */
	private function initFiles(PracticeCore $core) : void{
		$this->playerFolderPath = $core->getDataFolder() . 'players';

		if(!is_dir($this->playerFolderPath)){
			mkdir($this->playerFolderPath);
		}
	}

	/**
	 * @return array
	 */
	public function getCurrentLeaderboards() : array{
		$update = PracticeUtil::isMysqlEnabled();
		if($update === true) $this->updateLeaderboards();
		return $this->leaderboards;
	}

	/**
	 * @return void
	 */
	public function updateLeaderboards() : void{
		$result = [];

		$duelKits = PracticeCore::getKitHandler()->getDuelKitNames(true);

		$size = count($duelKits);

		for($i = 0; $i < $size; $i++)
			$duelKits[$i] = TextFormat::clean($duelKits[$i]);

		$mysqlEnabled = PracticeUtil::isMysqlEnabled();

		if(!$mysqlEnabled){

			// TODO:
			//$task = new AsyncUpdateLeaderboards($this->playerFolderPath, PracticeUtil::isMysqlEnabled(), $duelKits);
			//$this->server->getAsyncPool()->submitTask($task);

		}else{

			foreach($duelKits as $name){

				$uncoloredName = PracticeUtil::getUncoloredString($name);

				$leaderboard = $this->getLeaderboardsFrom($uncoloredName);

				$result[$uncoloredName] = $leaderboard;
			}

			$global = $this->getLeaderboardsFrom();

			$result['global'] = $global;

			$this->setLeaderboards($result);
		}
	}

	/**
	 * @param string $queue
	 *
	 * @return string[]
	 */
	public function getLeaderboardsFrom(string $queue = 'global') : array{
		$enabled = PracticeUtil::isMysqlEnabled();

		$result = [];

		$format = "\n" . TextFormat::GRAY . '%spot%. ' . TextFormat::AQUA . '%player% ' . TextFormat::WHITE . '(%elo%)';

		if($enabled === false){
			$sortedElo = $this->listEloForAll($queue);
			$playerNames = array_keys($sortedElo);
			$size = count($sortedElo) - 1;
			$subtracted = ($size > 10) ? 9 : $size;

			$len = $size - $subtracted;
			for($i = $size; $i >= $len; $i--){
				$place = $size - $i;
				$name = strval($playerNames[$i]);
				$elo = intval($sortedElo[$name]);
				$string = PracticeUtil::str_replace($format, ['%spot%' => $place + 1, '%player%' => $name, '%elo%' => $elo]);
				$result[] = $string;
			}

			$size = count($result);

			if($size > 10){
				for($i = $size; $i > 9; $i--){
					if(isset($result[$i])) unset($result[$i]);
				}
			}

		}else{
			$leaderboard = PracticeCore::getMysqlHandler()->getLeaderboardsFrom($queue);
			$size = count($leaderboard);
			$keys = array_keys($leaderboard);

			for($i = 0; $i < $size; $i++){
				$name = strval($keys[$i]);
				$elo = intval($leaderboard[$name]);
				$string = PracticeUtil::str_replace($format, ['%spot%' => $i + 1, '%player%' => $name, '%elo%' => $elo]);
				$result[] = $string;
			}
		}

		return $result;
	}

	/**
	 * @param string $queue
	 *
	 * @return array
	 */
	private function listEloForAll(string $queue) : array{
		$player_array = [];

		if(is_dir($this->playerFolderPath)){

			$files = scandir($this->playerFolderPath);

			foreach($files as $file){

				$file = strval($file);

				if(PracticeUtil::str_contains('.yml', $file)){

					$name = strval(str_replace('.yml', '', $file));

					$stats = $this->getStatsFrom($name);

					$elo = $stats['elo'];

					$resElo = 0;

					if($queue === 'global'){

						$total = 0;

						$count = count($elo);

						$keys = array_keys($elo);

						foreach($keys as $q)
							$total += intval($elo[$q]);

						$resElo = ($count !== 0) ? intval($total / $count) : 1000;

					}else{
						if(isset($elo[$queue])) $resElo = intval($elo[$queue]);
					}

					$player_array[$name] = $resElo;
				}
			}
		}

		asort($player_array);
		return $player_array;
	}

	/**
	 * @param string $player
	 *
	 * @return array
	 */
	private function getStatsFrom(string $player) : array{

		$enabled = PracticeUtil::isMysqlEnabled();

		$result = [];

		if($enabled === false){

			if($player !== 'None'){

				$data = $this->getPlayerData($player);
				$stats = $data['stats'];

				if(is_array($stats))
					$result = $stats;

			}
		}else $result = PracticeCore::getMysqlHandler()->getStats($player);

		return $result;
	}

	/**
	 * @param $player
	 *
	 * @return array
	 */
	public function getPlayerData($player) : array{

		$name = null;

		$data = [];

		if(isset($player) and !is_null($player)){
			if($player instanceof Player){
				$name = $player->getName();
			}else if($player instanceof PracticePlayer){
				$name = $player->getPlayerName();
			}else if(is_string($player)){
				$name = $player;
			}
		}

		if(!is_null($name)){
			$path = $this->playerFolderPath . "/$name.yml";
			if(file_exists($path)){
				$d = yaml_parse_file($path);
				if(is_array($d)) $data = $d;
			}
		}

		return $data;
	}

	/**
	 * @param array $leaderboards
	 */
	public function setLeaderboards(array $leaderboards) : void{
		$this->leaderboards = $leaderboards;

	}

	/**
	 * @param string $player
	 * @param bool   $enable
	 *
	 * @return void
	 */
	public function enableScoreboard(string $player, bool $enable = true) : void{
		$this->setPlayerData($player, 'scoreboards-enabled', $enable);
	}

	/**
	 * @param string $player
	 * @param string $key
	 * @param        $value
	 *
	 * @return bool
	 */
	public function setPlayerData(string $player, string $key, $value) : bool{
		$executed = true;
		$path = $this->playerFolderPath . "/$player.yml";
		if(file_exists($path)){
			$data = yaml_parse_file($path);
			if(is_array($data) and isset($data[$key])){
				$data[$key] = $value;
				$executed = true;
			}
			yaml_emit_file($path, $data);

		}else{
			$this->createPlayerData($player);
			$executed = $this->setPlayerData($player, $key, $value);
		}
		return $executed;
	}

	/**
	 * @param string $player
	 * @param bool   $asyncIt
	 *
	 * @return void
	 */
	private function createPlayerData(string $player, bool $asyncIt = false) : void{
		$path = $this->playerFolderPath . "/$player.yml";

		$p = $this->players[$player];

		$address = $p->getPlayer()->getNetworkSession()->getIp();

		$encodedIP = PracticeCore::getIPHandler()->encodeIP($address);

		if($asyncIt === true){

			$server = Server::getInstance();

			$guestRank = RankHandler::$GUEST->getLocalizedName();

			$server->getAsyncPool()->submitTask(new CreatePDataTask($player, $this->playerFolderPath, $guestRank, $encodedIP, PracticeCore::getKitHandler()->getDuelKitNames(true)));

		}else{

			if(!file_exists($path)){

				$file = fopen($path, 'wb');

				fclose($file);

				$elo = [];

				$kits = PracticeCore::getKitHandler()->getDuelKitNames(true);

				$size = count($kits);

				if($size > 0){
					foreach($kits as $kit){
						$name = $kit;
						$elo[$name] = 1000;
					}
				}

				$data = [
					'aliases' => [$player],
					'stats' => [
						'kills' => 0,
						'deaths' => 0,
						'elo' => $elo
					],
					'muted' => false,
					'ranks' => [
						RankHandler::$GUEST->getLocalizedName()
					],
					'scoreboards-enabled' => true,
					'place-break' => false,
					'pe-only' => false,
					'ips' => [$encodedIP]
				];

				yaml_emit_file($path, $data);

			}else{

				$data = yaml_parse_file($path);

				$emit = false;

				if(!isset($data['scoreboards-enabled'])){
					$data['scoreboards-enabled'] = true;
					$emit = true;
				}

				if(!isset($data['place-break'])){
					$data['place-break'] = false;
					$emit = true;
				}

				if(!isset($data['pe-only'])){
					$data['pe-only'] = false;
					$emit = true;
				}

				if(!isset($data['ips'])){
					$data['ips'] = [$encodedIP];
					$emit = true;
				}

				if($emit === true) yaml_emit_file($path, $data);

				$stats = $data['stats'];

				$elo = $stats['elo'];

				$kitHandler = PracticeCore::getKitHandler();

				$duelKits = $kitHandler->getDuelKitNames();

				$keys = array_keys($elo);

				sort($keys);

				sort($duelKits);

				if($keys !== $duelKits){

					$difference = array_diff($duelKits, $keys);

					foreach($difference as $kit){

						if($kitHandler->isDuelKit($kit))
							$elo[$kit] = 1000;
						else{
							if(isset($elo[$kit]))
								unset($elo[$kit]);
						}
					}

					$stats['elo'] = $elo;

					$data['stats'] = $stats;

					$emit = true;
				}

				if($emit === true) yaml_emit_file($path, $data);
			}
		}
	}

	/**
	 * @param string $player
	 *
	 * @return bool
	 */
	public function isScoreboardEnabled(string $player) : bool{
		$result = true;
		$path = $this->playerFolderPath . "/$player.yml";
		if(file_exists($path)){
			$data = yaml_parse_file($path);
			if(is_array($data) and isset($data['scoreboards-enabled']))
				$result = boolval($data['scoreboards-enabled']);
		}
		return $result;
	}

	/**
	 * @param string $player
	 * @param bool   $enable
	 *
	 * @return void
	 */
	public function setPlaceNBreak(string $player, bool $enable = false) : void{
		$this->setPlayerData($player, 'place-break', $enable);
	}

	/**
	 * @param string $player
	 *
	 * @return bool
	 */
	public function canPlaceNBreak(string $player) : bool{
		$result = false;
		$path = $this->playerFolderPath . "/$player.yml";
		if(file_exists($path)){
			$data = yaml_parse_file($path);
			if(is_array($data) and isset($data['place-break']))
				$result = boolval($data['place-break']);
		}
		return $result;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function unmutePlayer(string $name) : bool{
		return $this->mutePlayer($name, false);
	}

	/**
	 * @param string $name
	 * @param bool   $mute
	 *
	 * @return bool
	 */
	public function mutePlayer(string $name, bool $mute = true) : bool{
		return $this->setPlayerData($name, 'muted', $mute);
	}

	/**
	 * @param string $playerName
	 * @param bool   $peOnly
	 *
	 * @return void
	 */
	public function setPEOnlySetting(string $playerName, bool $peOnly = true) : void{
		$this->setPlayerData($playerName, 'pe-only', $peOnly);
	}

	/**
	 * @param string $playerName
	 *
	 * @return bool
	 */
	public function canQueuePEOnly(string $playerName) : bool{
		$result = false;
		$path = $this->playerFolderPath . "/$playerName.yml";
		if(file_exists($path)){
			$data = yaml_parse_file($path);
			if(is_array($data) and isset($data['pe-only']))
				$result = boolval($data['pe-only']);
		}

		if($result === true){
			if($this->isPlayerOnline($playerName)){
				$p = $this->getPlayer($playerName);
				$result = $p->peOnlyQueue();
			}
		}

		return $result;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isPlayerOnline($player) : bool{

		$result = false;

		if($this->isPlayer($player)){
			$p = $this->getPlayer($player);
			$result = $p->isOnline();
		}

		return $result;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isPlayer($player) : bool{
		return !is_null($this->getPlayer($player));
	}

	/**
	 * @param $player -> Must be of types: string, PracticePlayer, or Player
	 *
	 * @return PracticePlayer|null
	 */
	public function getPlayer($player) : ?PracticePlayer{
		$res = null;
		if($this->hasIndex($player)){
			$index = $this->getIndex($player);
			$test = $this->players[$index];
			if($test instanceof PracticePlayer){
				$res = $test;
			}
		}
		return $res;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	private function hasIndex($player) : bool{
		return $this->getIndex($player) !== '';
	}

	/**
	 * @param $player
	 *
	 * @return string
	 */
	private function getIndex($player) : string{
		$res = '';
		$name = null;

		if(isset($player) and !is_null($player)){
			if(is_string($player)){
				$testPlayer = Server::getInstance()->getPlayerExact($player);
				$name = $testPlayer?->getName();
			}elseif($player instanceof Player){
				$name = $player->getName();
			}elseif($player instanceof PracticePlayer){
				$name = $player->getPlayerName();
			}elseif($player instanceof Entity){
				$id = $player->getId();
				$p = PracticeUtil::getPlayerByID($id);
				if(!is_null($p)) $name = $p->getName();
			}
		}

		if(!is_null($name) and isset($this->players[$name]))
			$res = $name;

		return $res;
	}

	/**
	 * @param string $name
	 *
	 * @return bool
	 */
	public function isPlayerMuted(string $name) : bool{
		$path = $this->playerFolderPath . "/$name.yml";
		$result = false;
		if(file_exists($path)){
			$data = yaml_parse_file($path);
			if(is_array($data) and isset($data['muted'])){
				$result = $data['muted'];
			}
		}
		return $result;
	}

	/**
	 * @param string $player
	 *
	 * @return array
	 */
	public function getIps(string $player) : array{
		return $this->getPlayerData($player)['ips'];
	}

	/**
	 * @param string $name
	 * @param int    $device
	 * @param int    $controls
	 * @param int    $clientID
	 * @param string $deviceID
	 * @param string $deviceModel
	 *
	 * @return void
	 */
	public function putPendingPInfo(string $name, int $device, int $controls, int $clientID, string $deviceID, string $deviceModel) : void{
		$this->pendingDeviceData[$name] = [
			'device' => $device,
			'controls' => $controls,
			'device-id' => $deviceID,
			'client-id' => $clientID,
			'device-model' => $deviceModel
		];
	}

	/**
	 * @param Player $player
	 *
	 * @return PracticePlayer
	 */
	public function addPlayer(Player $player) : PracticePlayer{
		$name = $player->getName();

		$deviceOS = -1;
		$controls = -1;
		$deviceID = '';
		$clientID = -1;
		$deviceModel = 'unknown';

		if(isset($this->pendingDeviceData[$name])){
			$pendingDeviceData = $this->pendingDeviceData[$name];

			$deviceOS = intval($pendingDeviceData['device']);
			$deviceID = strval($pendingDeviceData['device-id']);
			$clientID = intval($pendingDeviceData['client-id']);
			$controls = intval($pendingDeviceData['controls']);
			$deviceModel = strval($pendingDeviceData['device-model']);
		}

		$p = new PracticePlayer($player, $deviceOS, $controls, $deviceID, $clientID, $deviceModel);

		$this->players[$name] = $p;

		$server = PracticeCore::getInstance()->getServer();

		if(PracticeUtil::isMysqlEnabled()) $server->getAsyncPool()->submitTask(new AddToDatabaseTask($name));

		$this->createPlayerData($name);

		$rankHandler = PracticeCore::getRankHandler();

		if(!$rankHandler->hasRanks($p)){
			$rankHandler->setDefaultRank($p);
		}else PracticeCore::getPermissionHandler()->updatePermissions($p);

		$address = $player->getNetworkSession()->getIp();

		$this->updateAliases($address, $name);

		return $p;
	}

	/**
	 * @param string $address
	 * @param string $name
	 *
	 * @return void
	 */
	private function updateAliases(string $address, string $name) : void{
		$data = $this->getPlayerData($name);

		if(isset($data['ips']) and isset($data['aliases'])){

			$edited = false;

			$ipsEncoded = $data['ips'];

			$ipHandler = PracticeCore::getIPHandler();

			$ips = $ipHandler->decodeIPsFromArr($ipsEncoded);

			$size = count($ips);
			$path = $this->playerFolderPath . "/$name.yml";

			if($size > 1 and !PracticeUtil::arr_contains_value($address, $ips)){
				$ipsEncoded[] = $ipHandler->encodeIP($address);
				$data['ips'] = $ipsEncoded;
				$edited = true;
			}

			if($edited === true) yaml_emit_file($path, $data);
		}
	}

	/**
	 * @param Player $player
	 *
	 * @return void
	 */
	public function removePlayer(Player $player) : void{
		$name = $player->getName();

		if(isset($this->players[$name])) unset($this->players[$name]);
		if(isset($this->pendingDeviceData[$name])) unset($this->pendingDeviceData[$name]);
	}

	/**
	 * @return int
	 */
	public function getPlayersInFights() : int{
		$count = 0;
		$duelHandler = PracticeCore::getDuelHandler();
		foreach($this->getOnlinePlayers() as $player){
			if($player instanceof PracticePlayer){
				if($player->isInDuel()){
					$duel = $duelHandler->getDuel($player->getPlayerName());
					if($duel->isDuelRunning()) $count++;
				}
			}
		}
		return $count;
	}

	/**
	 * @return PracticePlayer[]
	 */
	public function getOnlinePlayers() : array{
		$res = [];

		$keys = array_keys($this->players);

		foreach($keys as $key){
			if(isset($this->players[$key])){
				$player = $this->players[$key];
				if(!is_null($player) and $player instanceof PracticePlayer and $player->isOnline()){
					$res[] = $player;
				}
			}
		}
		return $res;
	}

	/**
	 * @return array
	 */
	public function getOnlineStaff() : array{
		$res = [];

		$keys = array_keys($this->players);

		foreach($keys as $key){
			if(isset($this->players[$key])){
				$player = $this->players[$key];
				if(!is_null($player) and $player instanceof PracticePlayer and $player->isOnline()){
					if($this->isStaffMember($player)) $res[] = $player->getPlayerName();
				}
			}
		}

		return $res;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isStaffMember($player) : bool{
		$result = false;
		$name = PracticeUtil::getPlayerName($player);

		if(!is_null($name)){
			$result = PracticeCore::getRankHandler()->hasStaffRank($name);

			if($result !== true and $this->isPlayerOnline($player)){
				$p = $this->getPlayer($player);
				$result = Server::getInstance()->isOp($p->getPlayerName());
			}
		}
		return $result;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isAdmin($player) : bool{
		$result = false;
		$name = PracticeUtil::getPlayerName($player);

		if(!is_null($name)) $result = PracticeCore::getRankHandler()->hasRank($name, RankHandler::$ADMIN);

		return $result;

	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isMod($player) : bool{
		$result = false;
		$name = PracticeUtil::getPlayerName($player);

		if(!is_null($name)) $result = PracticeCore::getRankHandler()->hasRank($name, RankHandler::$MODERATOR);

		return $result;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isBuilder($player) : bool{
		$result = false;
		$name = PracticeUtil::getPlayerName($player);

		if(!is_null($name)) $result = PracticeCore::getRankHandler()->hasRank($name, RankHandler::$BUILDER);

		return $result;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isOwner($player) : bool{
		$result = false;
		$name = PracticeUtil::getPlayerName($player);
		$rankHandler = PracticeCore::getRankHandler();

		if(!is_null($name)) $result = $rankHandler->hasRank($name, RankHandler::$DEV) or $rankHandler->hasRank($name, RankHandler::$OWNER);

		return $result;
	}

	/**
	 * @param $player
	 *
	 * @return bool
	 */
	public function isContentCreator($player) : bool{
		$result = false;
		$name = PracticeUtil::getPlayerName($player);

		if(!is_null($name)) $result = PracticeCore::getRankHandler()->hasFamousOrYTRank($name);

		return $result;
	}

	/**
	 * @param string $kit
	 *
	 * @return void
	 */
	public function addEloKit(string $kit) : void{
		$dir = $this->playerFolderPath;
		if(is_dir($dir)){
			$files = scandir($dir);
			foreach($files as $file){
				if(PracticeUtil::str_contains('.yml', strval($file))){
					$playerName = strval(str_replace('.yml', '', $file));
					$data = $this->getPlayerData($playerName);
					if(PracticeUtil::arr_contains_keys($data, 'stats')){
						$stats = $data['stats'];
						if(PracticeUtil::arr_contains_keys($stats, 'elo')){
							$elo = $stats['elo'];
							$elo[$kit] = 1000;
							$stats['elo'] = $elo;
						}
						$data['stats'] = $stats;
						yaml_emit_file($dir . "/$file", $data);
					}
				}
			}
		}
	}

	/**
	 * @param string $kit
	 *
	 * @return void
	 */
	public function removeEloKit(string $kit) : void{
		$dir = $this->playerFolderPath;
		if(is_dir($dir)){
			$files = scandir($dir);
			foreach($files as $file){
				if(PracticeUtil::str_contains('.yml', strval($file))){
					$playerName = strval(str_replace('.yml', '', $file));
					$data = $this->getPlayerData($playerName);
					if(PracticeUtil::arr_contains_keys($data, 'stats')){
						$stats = $data['stats'];
						if(PracticeUtil::arr_contains_keys($stats, 'elo')){
							$elo = $stats['elo'];
							unset($elo[$kit]);
							$stats['elo'] = $elo;
						}
						$data['stats'] = $stats;
						yaml_emit_file($dir . "/$file", $data);
					}
				}
			}
		}
	}

	/**
	 * @param string $player
	 * @param bool   $form = true
	 *
	 * @return string[];
	 */
	#[ArrayShape(['title' => "string", 'firstSeparator' => "string", 'kills' => "string", 'deaths' => "string", 'secondSeparator' => "string", 'eloTitle' => "string", 'thirdSeparator' => "string", 'elo' => "string", 'fourthSeparator' => "string"])] public function getStats(string $player, bool $form = true) : array{
		$stats = $this->getStatsFrom($player);

		$title = TextFormat::GOLD . '   » ' . TextFormat::BOLD . TextFormat::BLUE . 'Stats of ' . $player . TextFormat::RESET . TextFormat::GOLD . ' «';

		$k = $stats['kills'];

		$kills = TextFormat::GOLD . '   » ' . TextFormat::GREEN . 'Kills' . TextFormat::WHITE . ': ' . $k . TextFormat::GOLD . ' «';

		$d = $stats['deaths'];

		$deaths = TextFormat::GOLD . '   » ' . TextFormat::RED . 'Deaths' . TextFormat::WHITE . ': ' . $d . TextFormat::GOLD . ' «';

		$eloFormat = TextFormat::GOLD . '   » ' . TextFormat::AQUA . '{kit}' . TextFormat::WHITE . ': {elo}' . TextFormat::GOLD . ' «';

		unset($stats['kills'], $stats['deaths']);

		$eloTitle = TextFormat::GOLD . '   » ' . TextFormat::BOLD . TextFormat::BLUE . 'Elo of ' . $player . TextFormat::RESET . TextFormat::GOLD . ' «';

		$eloArray = [];

		$enable = PracticeUtil::isMysqlEnabled();

		if($enable === false) $stats = $stats['elo'];

		$keys = array_keys($stats);

		$kitArr = PracticeCore::getKitHandler()->getDuelKitNames(false, $enable === true, true);

		$eloStr = '';

		$count = 0;

		$len = count($keys) - 1;

		foreach($keys as $key){
			$key = strval($key);
			$name = $kitArr[$key];
			$elo = intval($stats[$key]);
			$line = ($count === $len) ? "" : "\n";
			$str = PracticeUtil::str_replace($eloFormat, ['{kit}' => $name, '{elo}' => $elo]) . $line;
			$eloArray[] = $str;
			$eloStr .= $str;
			$count++;
		}

		$arr = array_merge([$title, $kills, $deaths], $eloArray);

		$lineSeparator = TextFormat::GRAY . PracticeUtil::getLineSeparator($arr);

		return ['title' => $title, 'firstSeparator' => $lineSeparator, 'kills' => $kills, 'deaths' => $deaths, 'secondSeparator' => $lineSeparator, 'eloTitle' => $eloTitle, 'thirdSeparator' => $lineSeparator, 'elo' => $eloStr, 'fourthSeparator' => $lineSeparator];
	}

	/**
	 * @param string $player
	 *
	 * @return int
	 */
	public function addKillFor(string $player) : int{
		$enabled = PracticeUtil::isMysqlEnabled();

		if($enabled === false){
			$res = $this->getKillsOf($player) + 1;
			$this->updateStatsOf($player, 'kills', $res);
		}else $res = PracticeCore::getMysqlHandler()->addKill($player);

		return $res;
	}

	/**
	 * @param string $player
	 *
	 * @return int
	 */
	public function getKillsOf(string $player) : int{
		$stats = $this->getStatsFrom($player);
		$yamlRes = intval($stats['kills']);
		$enabled = PracticeUtil::isMysqlEnabled();
		return (($enabled === true) ? PracticeCore::getMysqlHandler()->getKills($player) : $yamlRes);
	}

	/**
	 * @param string $player
	 * @param string $key
	 * @param        $value
	 *
	 * @return void
	 */
	private function updateStatsOf(string $player, string $key, $value) : void{
		$stats = $this->getStatsFrom($player);
		if(PracticeUtil::str_contains('.', $key)){
			$split = explode('.', $key);
			if(PracticeUtil::arr_contains_keys($stats, $split[0])){
				$elo = $stats[$split[0]];
				if(PracticeUtil::arr_contains_keys($elo, $split[1])){
					$elo[$split[1]] = $value;
				}
				$stats[$split[0]] = $elo;
			}
		}else{
			if(PracticeUtil::arr_contains_keys($stats, $key)){
				$stats[$key] = $value;
			}
		}
		$data = $this->getPlayerData($player);
		$data['stats'] = $stats;
		$file = '/' . $player . '.yml';
		yaml_emit_file($this->playerFolderPath . $file, $data);
	}

	/**
	 * @param string $player
	 *
	 * @return int
	 */
	public function addDeathFor(string $player) : int{
		$enabled = PracticeUtil::isMysqlEnabled();

		if($enabled === false){
			$res = $this->getDeathsOf($player) + 1;
			$this->updateStatsOf($player, 'deaths', $res);
		}else $res = PracticeCore::getMysqlHandler()->addDeath($player);

		return $res;
	}

	/**
	 * @param string $player
	 *
	 * @return int
	 */
	public function getDeathsOf(string $player) : int{
		$stats = $this->getStatsFrom($player);
		$yamlRes = intval($stats['deaths']);
		$enabled = PracticeUtil::isMysqlEnabled();
		return ($enabled === true) ? PracticeCore::getMysqlHandler()->getDeaths($player) : $yamlRes;
	}

	/**
	 * @param string $winner
	 * @param string $loser
	 * @param string $queue
	 * @param int    $winnerDevice
	 * @param int    $loserDevice
	 *
	 * @return int[]
	 * @noinspection PhpArrayIndexImmediatelyRewrittenInspection
	 */
	#[ArrayShape(['winner' => "int", 'loser' => "float|int", 'winner-change' => "int", 'loser-change' => "float|int"])] public function setEloOf(string $winner, string $loser, string $queue, int $winnerDevice, int $loserDevice) : array{

		$result = ['winner' => 1000, 'loser' => 1000, 'winner-change' => 0, 'loser-change' => 0];

		$mysqlHandler = PracticeCore::getMysqlHandler();

		$enabled = PracticeUtil::isMysqlEnabled();

		$winnerElo = ($enabled === true) ? $mysqlHandler->getElo($winner, $queue) : $this->getEloFrom($winner, $queue);
		$loserElo = ($enabled === true) ? $mysqlHandler->getElo($loser, $queue) : $this->getEloFrom($loser, $queue);

		$kFactor = 32;

		$winnerExpectedScore = 1.0 / (1.0 + pow(10, floatval(($loserElo - $winnerElo) / 400)));
		$loserExpectedScore = abs(1.0 / (1.0 + pow(10, floatval(($winnerElo - $loserElo) / 400))));

		$newWinnerElo = $winnerElo + intval($kFactor * (1 - $winnerExpectedScore));
		$newLoserElo = $loserElo + intval($kFactor * (0 - $loserExpectedScore));

		$winnerEloChange = $newWinnerElo - $winnerElo;
		$loserEloChange = abs($loserElo - $newLoserElo);

		if($winnerDevice === PracticeUtil::WINDOWS_10 and $loserDevice !== PracticeUtil::WINDOWS_10)
			$loserEloChange = intval($loserEloChange * 0.9);
		else if($winnerDevice !== PracticeUtil::WINDOWS_10 and $loserDevice === PracticeUtil::WINDOWS_10)
			$winnerEloChange = intval($winnerEloChange * 1.1);

		$newWElo = $winnerElo + $winnerEloChange;
		$newLElo = $loserElo - $loserEloChange;

		if($newLElo < 700){
			$newLElo = 700;
			$loserEloChange = $loserElo - 700;
		}

		$result['winner'] = $newWElo;
		$result['loser'] = $newLElo;

		$result['winner-change'] = $winnerEloChange;
		$result['loser-change'] = $loserEloChange;

		$server = PracticeCore::getInstance()->getServer();

		if($enabled === true)
			$server->getAsyncPool()->submitTask(new UpdateEloTask($winner, $loser, $newWElo, $newLElo, $queue));
		else{
			$this->setElo($winner, $queue, $newWElo);
			$this->setElo($loser, $queue, $newLElo);
		}

		return $result;
	}

	/**
	 * @param string $player
	 * @param string $kit
	 *
	 * @return int
	 */
	public function getEloFrom(string $player, string $kit) : int{
		$stats = $this->getStatsFrom($player);
		$yamlRes = intval($stats['elo'][$kit]);
		$enabled = PracticeUtil::isMysqlEnabled();
		return (($enabled === true) ? PracticeCore::getMysqlHandler()->getElo($player, $kit) : $yamlRes);
	}

	/**
	 * @param string $player
	 * @param string $queue
	 * @param int    $value
	 *
	 * @return void
	 */
	private function setElo(string $player, string $queue, int $value) : void{
		$key = 'elo.' . $queue;
		$this->updateStatsOf($player, $key, $value);
	}

	/**
	 * @return void
	 */
	public function resetStats() : void{
		$dir = $this->playerFolderPath;
		if(is_dir($dir)){
			$files = scandir($dir);
			foreach($files as $file){
				$file = strval($file);
				if(PracticeUtil::str_contains('.yml', $file)){
					$playerName = str_replace('.yml', '', $file);
					$data = $this->getPlayerData($playerName);
					if(PracticeUtil::arr_contains_keys($data, 'stats')){

						$stats = $data['stats'];
						$stats['kills'] = 0;
						$stats['deaths'] = 0;
						$elo = $stats['elo'];
						$keys = array_keys($elo);

						foreach($keys as $key)
							$elo[$key] = 1000;

						$stats['elo'] = $elo;
						$data = $this->getPlayerData($playerName);
						$data['stats'] = $stats;
						$f = '/' . $file;
						yaml_emit_file($dir . $f, $data);
					}
				}
			}
		}

		PracticeUtil::broadcastMsg('All player stats have been reset!');
	}
}