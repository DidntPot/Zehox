<?php

namespace practice\manager\tasks;

use pocketmine\scheduler\AsyncTask;

class CreatePDataTask extends AsyncTask{
	/** @var string */
	private string $encodedIp;
	/** @var string */
	private string $playerName;

	/* @var array */
	private array $kits;

	/** @var string */
	private string $path;
	/** @var string */
	private string $guestRank;

	/**
	 * @param string $player
	 * @param string $path
	 * @param string $guestRank
	 * @param string $encodedIp
	 * @param array  $kits
	 */
	public function __construct(string $player, string $path, string $guestRank, string $encodedIp, array $kits){
		$this->path = $path . "/$player.yml";
		$this->playerName = $player;
		$this->encodedIp = $encodedIp;
		$this->guestRank = $guestRank;
		$this->kits = $kits;
	}

	/**
	 * @return void
	 */
	public function onRun() : void{
		if(!file_exists($this->path)){

			$file = fopen($this->path, 'wb');

			fclose($file);

			$elo = [];

			$size = count($this->kits);

			if($size > 0){
				foreach($this->kits as $kit){
					$name = strval($kit);
					$elo[$name] = 1000;
				}
			}

			$data = [
				'aliases' => [$this->playerName],
				'stats' => [
					'kills' => 0,
					'deaths' => 0,
					'elo' => $elo
				],
				'muted' => false,
				'ranks' => [
					$this->guestRank
				],
				'scoreboards-enabled' => true,
				'place-break' => false,
				'pe-only' => false,
				'ips' => [$this->encodedIp]
			];

			yaml_emit_file($this->path, $data);

		}else{

			$data = yaml_parse_file($this->path);

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
				$data['ips'] = [$this->encodedIp];
				$emit = true;
			}

			$stats = $data['stats'];

			$elo = $stats['elo'];

			$keys = array_keys($elo);

			sort($keys);

			$kits = $this->kits;

			sort($kits);

			if($keys !== $kits){

				$difference = array_diff($kits, $keys);

				foreach($difference as $kit){

					if($this->isDuelKit($kit))
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

			if($emit === true) yaml_emit_file($this->path, $data);
		}
	}

	/**
	 * @param string $kit
	 *
	 * @return bool
	 */
	private function isDuelKit(string $kit) : bool{
		return in_array($kit, $this->kits, false);
	}
}