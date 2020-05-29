<?php

declare(strict_types=1);

namespace Aeon\Calendar\Gregorian\Day;

use Webmozart\Assert\Assert;

/**
 * @psalm-immutable
 */
final class WeekDay
{
    private int $number;

    private const NAMES = [
        1 => 'Monday',
        2 => 'Tuesday',
        3 => 'Wednesday',
        4 => 'Thursday',
        5 => 'Friday',
        6 => 'Saturday',
        7 => 'Sunday',
    ];

    private const NAMES_SHORT = [
        1 => 'Mon',
        2 => 'Tue',
        3 => 'Wed',
        4 => 'Thu',
        5 => 'Fri',
        6 => 'Sat',
        7 => 'Sun',
    ];

    public function __construct(int $number)
    {
        Assert::greaterThanEq($number, 1);
        Assert::lessThanEq($number, 7);

        $this->number = $number;
    }

    /** @codeCoverageIgnore */
    public static function monday() : self
    {
        return new self(1);
    }

    /** @codeCoverageIgnore */
    public static function tuesday() : self
    {
        return new self(2);
    }

    /** @codeCoverageIgnore */
    public static function wednesday() : self
    {
        return new self(3);
    }

    /** @codeCoverageIgnore */
    public static function thursday() : self
    {
        return new self(4);
    }

    /** @codeCoverageIgnore */
    public static function friday() : self
    {
        return new self(5);
    }

    /** @codeCoverageIgnore */
    public static function saturday() : self
    {
        return new self(6);
    }

    /** @codeCoverageIgnore */
    public static function sunday() : self
    {
        return new self(7);
    }

    public function number() : int
    {
        return $this->number;
    }

    public function name() : string
    {
        return self::NAMES[$this->number];
    }

    public function shortName() : string
    {
        return self::NAMES_SHORT[$this->number];
    }

    public function equals(self $weekDay) : bool
    {
        return $this->number() === $weekDay->number();
    }

    public function isWeekend() : bool
    {
        return \in_array($this->number(), [6, 7], true);
    }
}
