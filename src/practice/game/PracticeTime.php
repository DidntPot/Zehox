<?php

declare(strict_types=1);

namespace practice\game;

use JetBrains\PhpStorm\ArrayShape;
use JetBrains\PhpStorm\Pure;
use practice\PracticeUtil;

class PracticeTime
{
    /** @var int */
    const ONE_YEAR_IN_MIN = 525600;
    /** @var float */
    const ONE_MONTH_IN_MIN = 43800.048;
    /** @var int */
    const ONE_DAY_IN_MIN = 1440;
    /** @var int */
    const ONE_HOUR_IN_MIN = 60;

    /** @var int */
    private int $day;
    /** @var int */
    private int $month;
    /** @var int */
    private int $year;
    /** @var int */
    private int $hour;
    /** @var int */
    private int $minute;
    /** @var int */
    private int $second;

    /** @var int */
    private int $totalMinTime;

    public function __construct()
    {
        $date = localtime(time(), true);

        $this->year = intval($date['tm_year']) + 1900;

        $this->month = intval($date['tm_mon']) + 1;

        $this->day = intval($date['tm_mday']);

        $this->hour = intval($date['tm_hour']);

        $this->minute = intval($date['tm_min']);

        $this->second = intval($date['tm_sec']);

        $this->totalMinTime = $this->initTotalMinTime();
    }

    /**
     * @return int
     */
    private function initTotalMinTime(): int
    {
        return intval(abs(((($this->year - 2018) * self::ONE_YEAR_IN_MIN) + ($this->month * self::ONE_MONTH_IN_MIN) + ($this->day * self::ONE_DAY_IN_MIN) + ($this->hour * self::ONE_HOUR_IN_MIN)) + $this->minute));
    }

    /**
     * @param array $arr
     * @return PracticeTime
     */
    public static function parseTime(array $arr): PracticeTime
    {
        $result = new PracticeTime();

        if (PracticeUtil::arr_contains_keys($arr, 'date', 'time')) {

            $date = strval($arr['date']);

            $time = strval($arr['time']);

            $splitDate = explode("/", $date);

            $splitTime = explode(':', PracticeUtil::str_replace($time, [' PST' => '']));

            $hours = intval($splitTime[0]);

            $mins = intval($splitTime[1]);

            $secs = intval($splitTime[2]);

            $month = intval($splitDate[0]);

            $day = intval($splitDate[1]);

            $yr = intval($splitDate[2]);

            $result = $result->setTimeValues($day, $month, $yr, $hours, $mins, $secs);
        }

        return $result;
    }

    /**
     * @param int $day
     * @param int $month
     * @param int $year
     * @param int $hour
     * @param int $min
     * @param int $sec
     * @return PracticeTime
     */
    private function setTimeValues(int $day, int $month, int $year, int $hour, int $min, int $sec): PracticeTime
    {
        $this->year = $year;
        $this->minute = $min;
        $this->second = $sec;
        $this->day = $day;
        $this->month = $month;
        $this->hour = $hour;
        $this->totalMinTime = $this->initTotalMinTime();
        return $this;
    }

    /**
     * @param string $key
     * @param int $value
     * @return $this
     */
    public function add(string $key, int $value): self
    {

        switch ($key) {
            case 'hr':
                $this->hour += $value;
                break;
            case 'day':
            case 'min':
                $this->minute += $value;
                break;
            case 'mon':
                $this->month += $value;
                break;
            case 'yr':
                $this->year += $value;
                break;
        }

        $this->totalMinTime = $this->initTotalMinTime();

        return $this;
    }

    /**
     * @return int
     */
    public function getDay(): int
    {
        return $this->day;
    }

    /**
     * @return int
     */
    public function getMonth(): int
    {
        return $this->month;
    }

    /**
     * @return int
     */
    public function getYear(): int
    {
        return $this->year;
    }

    /**
     * @return int
     */
    public function getHour(): int
    {
        return $this->hour;
    }

    /**
     * @return int
     */
    public function getMinute(): int
    {
        return $this->minute;
    }

    /**
     * @return int
     */
    public function getSecond(): int
    {
        return $this->second;
    }

    /**
     * @param PracticeTime $time
     * @return bool
     */
    #[Pure] public function isInLastMonth(PracticeTime $time): bool
    {
        $total = $time->getTotalTime();

        $difference = intval(abs($total - $this->getTotalTime()));

        return (($difference <= self::ONE_MONTH_IN_MIN) and $difference >= 0);
    }

    /**
     * @return int
     */
    public function getTotalTime(): int
    {
        return $this->totalMinTime;
    }

    /**
     * @param PracticeTime $time
     * @return bool
     */
    #[Pure] public function isInLastHour(PracticeTime $time): bool
    {
        $total = $time->getTotalTime();

        $difference = intval(abs($total - $this->getTotalTime()));

        return (($difference <= self::ONE_HOUR_IN_MIN) and $difference >= 0);
    }

    /**
     * @param PracticeTime $time
     * @return bool
     */
    #[Pure] public function isInLastDay(PracticeTime $time): bool
    {
        $total = $time->getTotalTime();

        $difference = intval(abs($total - $this->getTotalTime()));

        return (($difference <= self::ONE_DAY_IN_MIN) and $difference >= 0);
    }

    /**
     * @param PracticeTime $time
     * @return bool
     */
    #[Pure] public function isInLastYear(PracticeTime $time): bool
    {
        $total = $time->getTotalTime();

        $difference = intval(abs($total - $this->getTotalTime()));

        return $difference <= self::ONE_YEAR_IN_MIN and $difference >= 0;
    }

    /**
     * @return string[]
     */
    #[Pure] #[ArrayShape(['date' => "string", 'time' => "string"])] public function toMap(): array
    {
        return [
            'date' => $this->formatDate(),
            'time' => $this->formatTime()
        ];
    }

    /**
     * @param bool $file
     * @return string
     */
    #[Pure] public function formatDate(bool $file = true): string
    {
        if ($file) {

            $result = "$this->month/$this->day/$this->year";

        } else {

            $month = $this->monthToString();

            $result = "$month $this->day, $this->year";

        }

        return $result;
    }

    /**
     * @return string
     */
    private function monthToString(): string
    {
        return match ($this->month) {
            1 => true ? 'Jan' : 'January',
            2 => true ? 'Feb' : 'February',
            3 => true ? 'Mar' : 'March',
            4 => true ? 'Apr' : 'April',
            5 => 'May',
            6 => true ? 'Jun' : 'June',
            7 => true ? 'Jul' : 'July',
            8 => true ? 'Aug' : 'August',
            9 => true ? 'Sept' : 'September',
            10 => true ? 'Oct' : 'October',
            11 => true ? 'Nov' : 'November',
            12 => true ? 'Dec' : 'December',
            default => '',
        };
    }

    /**
     * @param bool $file
     * @return string
     */
    #[Pure] public function formatTime(bool $file = true): string
    {
        if ($file) {

            $hr = $this->formatNum($this->hour);

            $min = $this->formatNum($this->minute);

            $sec = $this->formatNum($this->second);

            $result = "$hr:$min:$sec PST";

        } else {

            $timeOfDay = $this->hour > 11 ? 'pm' : 'am';

            $hr = ($this->hour > 12 or $this->hour === 0) ? intval(abs($this->hour - 12)) : $this->hour;

            $hour = $this->formatNum($hr);

            $min = $this->formatNum($this->minute);

            $sec = $this->formatNum($this->second);

            $result = "$hour:$min:$sec$timeOfDay PST";

        }

        return $result;
    }

    /**
     * @param int $num
     * @return string
     */
    private function formatNum(int $num): string
    {
        $result = "$num";
        if ($num < 10) $result = "0$num";
        return $result;
    }

    /**
     * @param bool $ban
     * @return string
     */
    public function dateForFile(bool $ban = false): string
    {
        $hour = $this->hour > 11 ? 'pm' : 'am';
        $extended = ($ban === true) ? 'at ' . $hour : 'Reports';
        return "$this->month-$this->day-$this->year " . $extended;
    }

    /**
     * @return string
     */
    #[Pure] public function formatToSql(): string
    {
        $day = $this->formatNum($this->day);
        $hour = $this->formatNum($this->hour);
        $min = $this->formatNum($this->minute);
        $sec = $this->formatNum($this->second);
        return "$this->year-$this->month-$day $hour:$min:$sec";
    }
}