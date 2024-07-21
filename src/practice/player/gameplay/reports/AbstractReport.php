<?php

namespace practice\player\gameplay\reports;

use practice\game\PracticeTime;
use practice\PracticeUtil;

abstract class AbstractReport{
	/** @var string */
	public const string STAFF_NONE = "None";
	/** @var PracticeTime */
	protected PracticeTime $time;
	/** @var string|null */
	protected ?string $reporter;
	/** @var string */
	protected string $description;
	/** @var int */
	protected int $type;

	/**
	 * @param                   $reporter
	 * @param int               $type
	 * @param string            $description
	 * @param PracticeTime|null $time
	 */
	public function __construct($reporter, int $type, string $description = "", PracticeTime $time = null){
		$this->reporter = (isset($reporter) and !is_null(PracticeUtil::getPlayerName($reporter))) ? PracticeUtil::getPlayerName($reporter) : PracticeUtil::genAnonymousName();
		if(is_null($this->reporter)) $this->reporter = PracticeUtil::genAnonymousName();

		$this->description = $description;

		$this->type = $type;

		$this->time = (isset($time) and !is_null($time)) ? $time : new PracticeTime();
	}

	/**
	 * @param array $reportInfo
	 *
	 * @return BugReport|HackReport|StaffReport|null
	 */
	public static function parseReport(array $reportInfo) : BugReport|HackReport|StaffReport|null{
		$result = null;

		if(PracticeUtil::arr_contains_keys($reportInfo, "report-type", "time-stamp", "info")){

			$infoData = $reportInfo["info"];

			$timeStamp = $reportInfo["time-stamp"];

			$timeStampObject = PracticeTime::parseTime($timeStamp);

			$reportType = self::getReportTypeFromStr($reportInfo["report-type"]);

			if($reportType === ReportInfo::REPORT_BUG){

				$reporter = self::STAFF_NONE;

				if(PracticeUtil::arr_contains_keys($reportInfo, "reporter"))
					$reporter = $reportInfo["reporter"];

				if($reporter !== self::STAFF_NONE and is_array($infoData) and PracticeUtil::arr_contains_keys($infoData, "occurrence", "description")){

					$desc = strval($infoData["description"]);

					$occurrence = strval($infoData["occurrence"]);

					$result = new BugReport($reporter, $occurrence, $desc, $timeStampObject);
				}

			}elseif($reportType === ReportInfo::REPORT_HACK){

				if(is_array($infoData)){

					$reporter = strval($infoData["reporter"]);

					$reported = strval($infoData["reported"]);

					$desc = strval($infoData["reason"]);

					$result = new HackReport($reporter, $reported, $desc, $timeStampObject);
				}

			}elseif($reportType === ReportInfo::REPORT_STAFF){

				if(is_array($infoData)){

					$reporter = strval($infoData["reporter"]);

					$reported = strval($infoData["reported"]);

					$desc = strval($infoData["reason"]);

					$result = new StaffReport($reporter, $reported, $desc, $timeStampObject);
				}
			}
		}

		return $result;
	}

	/**
	 * @param string $report
	 *
	 * @return int
	 */
	public static function getReportTypeFromStr(string $report) : int{
		return match ($report) {
			"bug-report" => ReportInfo::REPORT_BUG,
			"hacker-report" => ReportInfo::REPORT_HACK,
			"staff-report" => ReportInfo::REPORT_STAFF,
			default => -1,
		};
	}

	/**
	 * @return int
	 */
	public function getType() : int{
		return $this->type % 3;
	}

	/**
	 * @return string
	 */
	public function getReporter() : string{
		return $this->reporter;
	}

	/**
	 * @return PracticeTime
	 */
	public function getTime() : PracticeTime{
		return $this->time;
	}

	/**
	 * @return string
	 */
	public function getDescription() : string{
		return $this->description;
	}

	/**
	 * @return bool
	 */
	public function hasDescription() : bool{
		return $this->description !== "" and $this->description !== "None";
	}

	/**
	 * @return array
	 */
	abstract public function toMap() : array;

	/**
	 * @return string
	 */
	abstract public function toMessage() : string;

	/**
	 * @param bool $writeFile
	 *
	 * @return string
	 */
	protected function getReportTypeToStr(bool $writeFile = true) : string{
		return match ($this->type) {
			ReportInfo::REPORT_BUG => ($writeFile ? "bug-report" : "Bug Report"),
			ReportInfo::REPORT_HACK => ($writeFile ? "hacker-report" : "Hacker Report"),
			ReportInfo::REPORT_STAFF => ($writeFile ? "staff-report" : "Staff Report"),
			default => "unknown",
		};
	}
}