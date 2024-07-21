<?php

declare(strict_types=1);

namespace practice\player\gameplay\reports;

class ReportInfo{
	/** @var int */
	const int LAST_HOUR = 0;
	/** @var int */
	const int LAST_DAY = 1;
	/** @var int */
	const int LAST_MONTH = 2;
	/** @var int */
	const int ALL_TIME = 3;

	/** @var int */
	public const int REPORT_BUG = 0;
	/** @var int */
	public const int REPORT_HACK = 1;
	/** @var int */
	public const int REPORT_STAFF = 2;
	/** @var int */
	const int ALL_REPORTS = 3;

	/**
	 * @param int $reportType
	 *
	 * @return string
	 */
	public static function getReportName(int $reportType) : string{
		$reportType = $reportType % 4;
		return match ($reportType) {
			self::REPORT_BUG => "Bug-Reports",
			self::REPORT_STAFF => "Staff-Reports",
			self::REPORT_HACK => "Hacker-Reports",
			default => "Reports",
		};
	}
}