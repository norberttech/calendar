<?php

declare(strict_types=1);

namespace Aeon\Calendar\Gregorian;

use Aeon\Calendar\Exception\Exception;
use Aeon\Calendar\Exception\InvalidArgumentException;
use Aeon\Calendar\Gregorian\TimeZone\TimeOffset;
use Aeon\Calendar\TimeUnit;
use Aeon\Calendar\Unit;

/**
 * @psalm-immutable
 */
final class DateTime
{
    private Day $day;

    private Time $time;

    private ?TimeZone $timeZone;

    private TimeOffset $timeOffset;

    private ?\DateTimeImmutable $lazyDateTimeImmutable;

    private ?int $lazyUnixTimestamp;

    public function __construct(Day $day, Time $time, ?TimeZone $timeZone = null, ?TimeOffset $timeOffset = null)
    {
        $this->lazyDateTimeImmutable = null;
        $this->lazyUnixTimestamp = null;
        $this->day = $day;
        $this->time = $time;
        $this->timeZone = $timeZone;

        $this->timeOffset = $timeOffset === null
            ? ($timeZone !== null ? $timeZone->timeOffset($this) : TimeOffset::UTC())
            : $timeOffset;

        if ($this->timeZone !== null) {
            $this->timeOffset = $this->timeZone->timeOffset($this);
        }

        if ($this->timeZone === null && $this->timeOffset->isUTC()) {
            $this->timeZone = TimeZone::UTC();
        }
    }

    /**
     * @psalm-pure
     */
    public static function create(int $year, int $month, int $day, int $hour, int $minute, int $second, int $microsecond = 0, string $timezone = 'UTC') : self
    {
        return new self(
            new Day(
                new Month(
                    new Year($year),
                    $month
                ),
                $day
            ),
            new Time($hour, $minute, $second, $microsecond),
            new TimeZone($timezone)
        );
    }

    /**
     * @psalm-pure
     * @psalm-suppress ImpureMethodCall
     */
    public static function fromDateTime(\DateTimeInterface $dateTime) : self
    {
        try {
            $tz = TimeZone::fromDateTimeZone($dateTime->getTimezone());
        } catch (InvalidArgumentException $e) {
            $tz = null;
        }

        return new self(
            Day::fromDateTime($dateTime),
            Time::fromDateTime($dateTime),
            $tz,
            TimeOffset::fromTimeUnit(TimeUnit::seconds($dateTime->getOffset()))
        );
    }

    /**
     * @psalm-pure
     * @psalm-suppress ImpureFunctionCall
     */
    public static function fromString(string $date) : self
    {
        $currentPHPTimeZone = \date_default_timezone_get();

        \date_default_timezone_set('UTC');

        $dateTime = self::fromDateTime(new \DateTimeImmutable($date));

        \date_default_timezone_set($currentPHPTimeZone);

        return $dateTime;
    }

    /**
     * @psalm-pure
     * @psalm-suppress ImpureFunctionCall
     */
    public static function fromTimestampUnix(int $timestamp) : self
    {
        $currentPHPTimeZone = \date_default_timezone_get();

        \date_default_timezone_set('UTC');

        $dateTime = self::fromDateTime((new \DateTimeImmutable)->setTimestamp($timestamp));

        \date_default_timezone_set($currentPHPTimeZone);

        return $dateTime;
    }

    public function __toString() : string
    {
        return $this->toISO8601();
    }

    /**
     * @return array{datetime: string, day: Day, time: Time, offset: TimeOffset, timezone: ?TimeZone}
     */
    public function __debugInfo() : array
    {
        return [
            'datetime' => $this->toISO8601(),
            'day' => $this->day,
            'time' => $this->time,
            'offset' => $this->timeOffset,
            'timezone' => $this->timeZone,
        ];
    }

    /**
     * @return array{day: Day, time: Time, timeZone: ?TimeZone, timeOffset: TimeZone\TimeOffset}
     */
    public function __serialize() : array
    {
        return [
            'day' => $this->day,
            'time' => $this->time,
            'timeZone' => $this->timeZone,
            'timeOffset' => $this->timeOffset,
        ];
    }

    public function year() : Year
    {
        return $this->month()->year();
    }

    public function month() : Month
    {
        return $this->day()->month();
    }

    public function day() : Day
    {
        return $this->day;
    }

    public function time() : Time
    {
        return $this->time;
    }

    public function setTime(Time $time) : self
    {
        return new self(
            $this->day(),
            $time,
            $this->timeZone(),
            $this->timeOffset()
        );
    }

    public function setTimeIn(Time $time, TimeZone $timeZone) : self
    {
        return (new self(
            $this->day(),
            $time,
            $this->timeZone(),
            $this->timeOffset()
        ))->toTimeZone($timeZone)
            ->setTime($time);
    }

    public function setDay(Day $day) : self
    {
        return new self(
            $day,
            $this->time(),
            $this->timeZone(),
            $this->timeOffset()
        );
    }

    /** @psalm-suppress InaccessibleProperty */
    public function toDateTimeImmutable() : \DateTimeImmutable
    {
        if ($this->lazyDateTimeImmutable instanceof \DateTimeImmutable) {
            return $this->lazyDateTimeImmutable;
        }

        $this->lazyDateTimeImmutable = new \DateTimeImmutable(
            \sprintf(
                '%d-%02d-%02d %02d:%02d:%02d.%06d%s',
                $this->day->year()->number(),
                $this->day->month()->number(),
                $this->day->number(),
                $this->time->hour(),
                $this->time->minute(),
                $this->time->second(),
                $this->time->microsecond(),
                $this->timeZone === null ? $this->timeOffset->toString() : ''
            ),
            ($this->timeZone === null) ? null : $this->timeZone->toDateTimeZone()
        );
        $this->lazyUnixTimestamp = $this->lazyDateTimeImmutable->getTimestamp();

        return $this->lazyDateTimeImmutable;
    }

    public function toAtomicTime() : self
    {
        return $this->add(LeapSeconds::load()->until($this)->offsetTAI());
    }

    public function toGPSTime() : self
    {
        return $this->add(LeapSeconds::load()
            ->since(TimeEpoch::GPS()->date())
            ->until($this)->count());
    }

    public function format(string $format) : string
    {
        return $this->toDateTimeImmutable()->format($format);
    }

    public function timeZone() : ?TimeZone
    {
        return $this->timeZone;
    }

    public function timeOffset() : TimeOffset
    {
        return $this->timeOffset;
    }

    /**
     * @psalm-pure
     */
    public function toTimeZone(TimeZone $dateTimeZone) : self
    {
        return self::fromDateTime($this->toDateTimeImmutable()->setTimezone($dateTimeZone->toDateTimeZone()));
    }

    public function toISO8601(bool $extended = true) : string
    {
        return $extended
            ? $this->toDateTimeImmutable()->format('Y-m-d\TH:i:sP')
            : $this->toDateTimeImmutable()->format('Ymd\THisO');
    }

    public function isDaylightSaving() : bool
    {
        return (bool) $this->toDateTimeImmutable()->format('I');
    }

    public function isDaylight() : bool
    {
        return !$this->isDaylightSaving();
    }

    public function timestamp(TimeEpoch $timeEpoch) : TimeUnit
    {
        if ($this->isBefore($timeEpoch->date())) {
            throw new Exception('Given epoch started at ' . $timeEpoch->date()->toISO8601() . ' which was after ' . $this->toISO8601());
        }

        switch ($timeEpoch->type()) {
            case TimeEpoch::UTC:
                return $this->timestampUNIX()
                    ->sub(TimeEpoch::UNIX()->distanceTo($timeEpoch))
                    ->add(LeapSeconds::load()->offsetTAI());
            case TimeEpoch::GPS:
                return $this->timestampUNIX()
                    ->sub(TimeEpoch::UNIX()->distanceTo($timeEpoch))
                    ->add(
                        LeapSeconds::load()->offsetTAI()
                            ->sub(LeapSeconds::load()->until($timeEpoch->date())->offsetTAI())
                    );
            case TimeEpoch::TAI:
                return $timeEpoch->date()->until($this)->distance()
                    ->add($timeEpoch->date()->until($this)->leapSeconds()->offsetTAI());

            default:
                return $this->timestampUNIX();
        }
    }

    /**
     * Number of seconds elapsed since Unix epoch started at 1970-01-01 (1970-01-01T00:00:00Z)
     * Not including leap seconds.
     */
    public function timestampUNIX() : TimeUnit
    {
        /** @psalm-suppress InaccessibleProperty */
        if ($this->lazyUnixTimestamp === null) {
            $this->lazyUnixTimestamp = $this->toDateTimeImmutable()->getTimestamp();
        }

        return $this->lazyUnixTimestamp >= 0
            ? TimeUnit::positive($this->lazyUnixTimestamp, $this->time->microsecond())
            : TimeUnit::negative(\abs($this->lazyUnixTimestamp), $this->time->microsecond());
    }

    public function modify(string $modify) : self
    {
        return self::fromDateTime($this->toDateTimeImmutable()->modify($modify));
    }

    public function addHour() : self
    {
        return $this->modify('+1 hour');
    }

    public function subHour() : self
    {
        return $this->modify('-1 hour');
    }

    public function addHours(int $hours) : self
    {
        return $this->modify(\sprintf('+%d hour', $hours));
    }

    public function subHours(int $hours) : self
    {
        return $this->modify(\sprintf('-%d hour', $hours));
    }

    public function addMinute() : self
    {
        return $this->modify('+1 minute');
    }

    public function subMinute() : self
    {
        return $this->modify('-1 minute');
    }

    public function addMinutes(int $minutes) : self
    {
        return $this->modify(\sprintf('+%d minute', $minutes));
    }

    public function subMinutes(int $minutes) : self
    {
        return $this->modify(\sprintf('-%d minute', $minutes));
    }

    public function addSecond() : self
    {
        return $this->modify('+1 second');
    }

    public function subSecond() : self
    {
        return $this->modify('-1 second');
    }

    public function addSeconds(int $seconds) : self
    {
        return $this->modify(\sprintf('+%d second', $seconds));
    }

    public function subSeconds(int $seconds) : self
    {
        return $this->modify(\sprintf('-%d second', $seconds));
    }

    public function addDay() : self
    {
        return $this->modify('+1 day');
    }

    public function subDay() : self
    {
        return $this->modify('-1 day');
    }

    public function addDays(int $days) : self
    {
        return $this->modify(\sprintf('+%d day', $days));
    }

    public function subDays(int $days) : self
    {
        return $this->modify(\sprintf('-%d day', $days));
    }

    public function addMonth() : self
    {
        return $this->modify('+1 month');
    }

    public function subMonth() : self
    {
        return $this->modify('-1 month');
    }

    public function addMonths(int $months) : self
    {
        return $this->modify(\sprintf('+%d months', $months));
    }

    public function subMonths(int $months) : self
    {
        return $this->modify(\sprintf('-%d months', $months));
    }

    public function addYear() : self
    {
        return $this->modify('+1 year');
    }

    public function subYear() : self
    {
        return $this->modify('-1 year');
    }

    public function addYears(int $years) : self
    {
        return $this->modify(\sprintf('+%d years', $years));
    }

    public function subYears(int $years) : self
    {
        return $this->modify(\sprintf('-%d years', $years));
    }

    public function midnight() : self
    {
        return new self(
            $this->day(),
            new Time(0, 0, 0, 0),
            $this->timeZone(),
            $this->timeOffset()
        );
    }

    public function noon() : self
    {
        return new self(
            $this->day(),
            new Time(12, 0, 0, 0),
            $this->timeZone(),
            $this->timeOffset()
        );
    }

    public function endOfDay() : self
    {
        return new self(
            $this->day(),
            new Time(23, 59, 59, 999999),
            $this->timeZone(),
            $this->timeOffset()
        );
    }

    public function yesterday() : self
    {
        return $this->sub(TimeUnit::day())->midnight();
    }

    public function tomorrow() : self
    {
        return $this->add(TimeUnit::day())->midnight();
    }

    public function add(Unit $timeUnit) : self
    {
        return self::fromDateTime($this->toDateTimeImmutable()->add($timeUnit->toDateInterval()));
    }

    public function sub(Unit $timeUnit) : self
    {
        return self::fromDateTime($this->toDateTimeImmutable()->sub($timeUnit->toDateInterval()));
    }

    public function isEqual(self $dateTime) : bool
    {
        return $this->toDateTimeImmutable() == $dateTime->toDateTimeImmutable();
    }

    public function isAfter(self $dateTime) : bool
    {
        return $this->toDateTimeImmutable() > $dateTime->toDateTimeImmutable();
    }

    public function isAfterOrEqual(self $dateTime) : bool
    {
        return $this->toDateTimeImmutable() >= $dateTime->toDateTimeImmutable();
    }

    public function isBeforeOrEqual(self $dateTime) : bool
    {
        return $this->toDateTimeImmutable() <= $dateTime->toDateTimeImmutable();
    }

    public function isBefore(self $dateTime) : bool
    {
        return $this->toDateTimeImmutable() < $dateTime->toDateTimeImmutable();
    }

    public function until(self $dateTime) : TimePeriod
    {
        return new TimePeriod($this, $dateTime);
    }

    public function since(self $dateTime) : TimePeriod
    {
        return new TimePeriod($dateTime, $this);
    }

    public function distance(self $dateTime) : TimeUnit
    {
        return $this->until($dateTime)->distance();
    }

    public function distanceSince(self $dateTime) : TimeUnit
    {
        return $this->since($dateTime)->distance();
    }

    public function distanceUntil(self $dateTime) : TimeUnit
    {
        return $this->until($dateTime)->distance();
    }

    public function iterate(self $pointInTime, TimeUnit $by) : TimePeriods
    {
        return $pointInTime->isBefore($this)
            ? $this->since($pointInTime)->iterateBackward($by, Interval::closed())
            : $this->until($pointInTime)->iterate($by, Interval::closed());
    }

    public function isAmbiguous() : bool
    {
        $tz = $this->timeZone();

        if ($tz === null || $tz->timeOffset($this)->isUTC()) {
            return false;
        }

        /**
         * @var array<int, array{ts: int, time: string, offset: int, isdst: bool, abbr: string}> $transitions
         */
        $transitions = $tz->toDateTimeZone()->getTransitions(
            $this->timestampUNIX()->sub(TimeUnit::hours(1)->add(TimeUnit::minute()))->inSeconds(),
            $this->timestampUNIX()->add(TimeUnit::hours(1))->inSeconds(),
        );

        if (\count($transitions) === 1) {
            return false;
        }

        if ($transitions[1]['offset'] - $transitions[0]['offset'] === 3600) {
            return false;
        }

        return true;
    }

    public function quarter() : Quarter
    {
        return $this->year()->quarter((int) \ceil($this->month()->number() / 3));
    }
}
