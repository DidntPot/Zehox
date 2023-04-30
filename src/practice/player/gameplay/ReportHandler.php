<?php

declare(strict_types=1);

namespace practice\player\gameplay;

use practice\game\PracticeTime;
use practice\player\gameplay\reports\AbstractReport;
use practice\player\gameplay\reports\BugReport;
use practice\player\gameplay\reports\HackReport;
use practice\player\gameplay\reports\ReportInfo;
use practice\player\gameplay\reports\StaffReport;
use practice\PracticeCore;
use practice\PracticeUtil;

class ReportHandler{
	private $file;

	public function __construct(){
		$this->initFile();
	}

	/**
	 * @return void
	 */
	private function initFile() : void{
		$dataFolder = PracticeCore::getInstance()->getDataFolder();

		if(is_dir($dataFolder)){
			$file = $dataFolder . "/reports.yml";

			if(!file_exists($file)){
				$f = fopen($file, "wb");
				fclose($f);
				$data = [];
				yaml_emit_file($file, $data);
			}

			$this->file = $file;
		}
	}

	/**
	 * @param        $reporter
	 * @param string $occurrence
	 * @param string $description
	 *
	 * @return void
	 */
	public function createBugReport($reporter, string $occurrence, string $description) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($reporter)){

			$p = PracticeCore::getPlayerHandler()->getPlayer($reporter);

			$bugReport = new BugReport($p->getPlayer(), $occurrence, $description);

			$map = $bugReport->toMap();
			$key = $this->getLargestKey();

			$data = yaml_parse_file($this->file);

			$data[$key] = $map;

			yaml_emit_file($this->file, $data);
		}
	}

	/**
	 * @return string
	 */
	private function getLargestKey() : string{
		$data = yaml_parse_file($this->file);

		$greatest = 0;

		if(count($data) > 0){

			$keys = array_keys($data);

			foreach($keys as $key){

				$keyStr = strval($key);
				$replaced = PracticeUtil::str_replace($keyStr, ["report-" => ""]);

				$val = intval($replaced);

				if($val > $greatest){
					$greatest = $val;
				}
			}
		}

		$greatest = $greatest + 1;

		return "report-$greatest";
	}

	/**
	 * @param        $reporter
	 * @param string $reported
	 * @param string $reason
	 *
	 * @return void
	 */
	public function createHackerReport($reporter, string $reported, string $reason) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($reporter)){

			$p = PracticeCore::getPlayerHandler()->getPlayer($reporter);

			$hackReport = new HackReport($p->getPlayer(), $reported, $reason);

			$map = $hackReport->toMap();
			$key = $this->getLargestKey();

			$data = yaml_parse_file($this->file);

			$data[$key] = $map;

			yaml_emit_file($this->file, $data);
		}
	}

	/**
	 * @param        $reporter
	 * @param string $reported
	 * @param string $reason
	 *
	 * @return void
	 */
	public function createStaffReport($reporter, string $reported, string $reason) : void{
		if(PracticeCore::getPlayerHandler()->isPlayerOnline($reporter)){

			$p = PracticeCore::getPlayerHandler()->getPlayer($reporter);

			if(PracticeCore::getPlayerHandler()->isPlayerOnline($reported)){

				$staff = PracticeCore::getPlayerHandler()->getPlayer($reported);

				if(PracticeCore::getPlayerHandler()->isStaffMember($staff->getPlayerName())){

					$staffReport = new StaffReport($p->getPlayerName(), $staff->getPlayerName(), $reason);

					$map = $staffReport->toMap();
					$key = $this->getLargestKey();

					$data = yaml_parse_file($this->file);

					$data[$key] = $map;

					yaml_emit_file($this->file, $data);
				}
			}
		}
	}

	/**
	 * @param int $time
	 * @param int $type
	 *
	 * @return array
	 */
	public function getReports(int $time = ReportInfo::ALL_TIME, int $type = ReportInfo::ALL_REPORTS) : array{
		$reports = [];

		$results = [];

		$data = yaml_parse_file($this->file);

		$currentTime = new PracticeTime();

		$keys = array_keys($data);

		foreach($keys as $key){
			$repData = $data[$key];
			if(is_array($repData)){
				$report = AbstractReport::parseReport($repData);
				if(!is_null($report)){
					$reportTime = $report->getTime();
					if($time === ReportInfo::ALL_TIME){
						$reports[] = $report;
					}else{
						if($time === ReportInfo::LAST_HOUR){
							if($currentTime->isInLastHour($reportTime)) $reports[] = $report;
						}elseif($time === ReportInfo::LAST_MONTH){
							if($currentTime->isInLastMonth($reportTime)) $reports[] = $report;
						}elseif($time === ReportInfo::LAST_DAY){
							if($currentTime->isInLastDay($reportTime)) $reports[] = $report;
						}
					}
				}
			}
		}

		$count = count($reports);

		for($i = $count - 1; $i > -1; $i--){
			$val = $reports[$i];
			if($val instanceof AbstractReport){
				$t = $val->getType();
				if($type === ReportInfo::ALL_REPORTS){
					$results[] = $val;
				}else{
					if($t === $type) $results[] = $val;
				}
			}
		}

		return $results;
	}

	/**
	 * @param string $player
	 *
	 * @return array
	 */
	public function getReportsOf(string $player) : array{
		$reportData = yaml_parse_file($this->file);

		$keys = array_keys($reportData);

		$reports = [];

		foreach($keys as $key){
			$report = $reportData[$key];
			$parsed = AbstractReport::parseReport($report);
			if(!is_null($parsed)){
				$name = null;
				if($parsed instanceof StaffReport)
					$name = $parsed->getReportedStaff();
				elseif($parsed instanceof HackReport)
					$name = $parsed->getReportedPlayer();
				if($name !== null and $name === $player) $reports[] = $parsed;
			}
		}
		return $reports;
	}

	/**
	 * @return void
	 */
	public function clearReports() : void{
		yaml_emit_file($this->file, []);
	}
}