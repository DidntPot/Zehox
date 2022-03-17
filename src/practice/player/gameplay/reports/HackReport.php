<?php

namespace practice\player\gameplay\reports;

use JetBrains\PhpStorm\ArrayShape;
use practice\game\PracticeTime;
use practice\PracticeUtil;

class HackReport extends AbstractReport
{
    /** @var string|null */
    private ?string $reportedPlayer;

    /**
     * @param $reporter
     * @param $reported
     * @param string $description
     * @param PracticeTime|null $time
     */
    public function __construct($reporter, $reported, string $description = "", PracticeTime $time = null)
    {
        parent::__construct($reporter, ReportInfo::REPORT_HACK, $description, $time);
        $this->reportedPlayer = (isset($reported) and !is_null(PracticeUtil::getPlayerName($reported))) ? PracticeUtil::getPlayerName($reported) : parent::STAFF_NONE;
        if (is_null($this->reportedPlayer)) $this->reportedPlayer = parent::STAFF_NONE;
    }

    /**
     * @return bool
     */
    public function isReportedPlayerValid(): bool
    {
        return $this->reportedPlayer !== self::STAFF_NONE;
    }

    /**
     * @return array
     */
    #[ArrayShape(["report-type" => "string", "time-stamp" => "string[]", "info" => "array"])] public function toMap(): array
    {
        $timeStampArr = $this->time->toMap();

        $reportedType = $this->getReportTypeToStr();

        $reporter = $this->getReporter();
        $reported = $this->getReportedPlayer();

        $desc = ($this->description !== "" ? $this->description : parent::STAFF_NONE);

        $info = [
            "reporter" => $reporter,
            "reported" => $reported,
            "reason" => $desc
        ];

        $result = [
            "report-type" => $reportedType,
            "time-stamp" => $timeStampArr,
            "info" => $info
        ];

        return $result;
    }

    /**
     * @return string
     */
    public function getReportedPlayer(): string
    {
        return $this->reportedPlayer;
    }

    /**
     * @param bool $form
     * @return string
     */
    public function toMessage(bool $form = true): string
    {
        $reportType = "Hacker Report";

        $date = $this->time->formatDate(false);
        $time = $this->time->formatTime(false);

        $timeStamp = "$date at $time";

        $desc = "!";

        if ($this->hasDescription()) $desc = " for '$this->description.'";

        $addedLine = ($form === true) ? "\n" : " ";

        $format = "[$timeStamp]$addedLine$reportType - $this->reporter reported $this->reportedPlayer$desc";

        return $format;
    }
}