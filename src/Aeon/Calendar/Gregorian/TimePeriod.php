<?php

declare(strict_types=1);

namespace Aeon\Calendar\Gregorian;

use Aeon\Calendar\Exception\InvalidArgumentException;
use Aeon\Calendar\TimeUnit;
use Aeon\Calendar\Unit;

/**
 * @psalm-immutable
 */
final class TimePeriod
{
    private DateTime $start;

    private DateTime $end;

    public function __construct(DateTime $start, DateTime $end)
    {
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * @return array{start: DateTime, end: DateTime}
     */
    public function __serialize() : array
    {
        return [
            'start' => $this->start,
            'end' => $this->end,
        ];
    }

    public function start() : DateTime
    {
        return $this->start;
    }

    public function end() : DateTime
    {
        return $this->end;
    }

    public function isForward() : bool
    {
        return $this->distance()->isPositive();
    }

    public function isBackward() : bool
    {
        return $this->distance()->isNegative();
    }

    /**
     * Calculate distance between 2 points in time without leap seconds.
     */
    public function distance() : TimeUnit
    {
        $startUnixTimestamp = $this->start->timestampUNIX();
        $endUnixTimestamp = $this->end->timestampUNIX();

        $result = $endUnixTimestamp
            ->sub($startUnixTimestamp)
            ->toPositive();

        return $this->start->isAfter($this->end) ? $result->invert() : $result;
    }

    public function leapSeconds() : LeapSeconds
    {
        return LeapSeconds::load()->findAllBetween($this);
    }

    public function iterate(Unit $timeUnit, Interval $interval) : TimePeriods
    {
        /**
         * @var array<DateTime> $dateTimes
         * @psalm-suppress ImpureMethodCall
         */
        $dateTimes = \iterator_to_array(
            $interval->toIterator($this->start, $timeUnit, $this->end)
        );

        /** @psalm-suppress ImpureFunctionCall */
        return new TimePeriods(
            ...\array_filter(
                \array_map(
                    function (DateTime $start) use ($timeUnit, $interval) : ?self {
                        $end = $start->add($timeUnit);

                        if ($interval->isRightOpen()) {
                            if ($end->isAfter($this->end())) {
                                $end = $this->end()->sub($timeUnit);
                            }

                            if ($end->isEqual($this->end())) {
                                return null;
                            }
                        }

                        if ($interval->isClosed() || $interval->isLeftOpen()) {
                            if ($end->isAfter($this->end())) {
                                $end = $this->end();
                            }
                        }

                        if ($start->isAfterOrEqual($end) || $end->isAfter($this->end())) {
                            return null;
                        }

                        return new self(
                            $start,
                            $end
                        );
                    },
                    $dateTimes
                )
            )
        );
    }

    public function iterateBackward(Unit $timeUnit, Interval $interval) : TimePeriods
    {
        /**
         * @var array<DateTime> $dateTimes
         * @psalm-suppress ImpureMethodCall
         */
        $dateTimes = \iterator_to_array(
            $interval->toIteratorBackward($this->start, $timeUnit, $this->end)
        );

        /**
         * @psalm-suppress ImpureFunctionCall
         */
        return new TimePeriods(
            ...\array_filter(
                \array_map(
                    function (DateTime $start) use ($timeUnit, $interval) : ?self {
                        $end = $start->sub($timeUnit);

                        if ($interval->isRightOpen()) {
                            if ($start->isEqual($this->end())) {
                                return null;
                            }

                            if ($end->isBefore($this->start())) {
                                if ($start->isEqual($this->start())) {
                                    return null;
                                }

                                return new self($start, $this->start());
                            }
                        }

                        if ($start->isBefore($this->start())) {
                            return null;
                        }

                        if ($interval->isClosed()) {
                            if ($end->isBefore($this->start())) {
                                if ($start->isEqual($this->start())) {
                                    return null;
                                }

                                return new self($start, $this->start());
                            }
                        }

                        if ($interval->isLeftOpen()) {
                            if ($end->isBeforeOrEqual($this->start())) {
                                return null;
                            }
                        }

                        return new self($start, $end);
                    },
                    $dateTimes
                )
            )
        );
    }

    public function overlaps(self $timePeriod) : bool
    {
        if ($this->isBackward()) {
            $thisPeriodForward = $this->revert();
        } else {
            $thisPeriodForward = $this;
        }

        if ($timePeriod->isBackward()) {
            $otherPeriodForward = $timePeriod->revert();
        } else {
            $otherPeriodForward = $timePeriod;
        }

        $thisPeriodStart = $thisPeriodForward->start();
        $thisPeriodEnd = $thisPeriodForward->end();
        $otherPeriodStart = $otherPeriodForward->start();
        $otherPeriodEnd = $otherPeriodForward->end();

        if ($thisPeriodForward->abuts($otherPeriodForward)) {
            return false;
        }

        if ($thisPeriodStart->isBefore($otherPeriodStart) &&
            $thisPeriodEnd->isBefore($otherPeriodStart) &&
            $thisPeriodEnd->isBefore($otherPeriodEnd)
        ) {
            return false;
        }

        if ($thisPeriodEnd->isBefore($otherPeriodEnd)) {
            return true;
        }

        if ($thisPeriodStart->isAfter($otherPeriodStart) &&
            $thisPeriodStart->isBefore($otherPeriodEnd) &&
            $thisPeriodEnd->isAfter($otherPeriodStart)
        ) {
            return true;
        }

        if ($thisPeriodStart->isAfter($otherPeriodStart) &&
            $thisPeriodEnd->isAfter($otherPeriodStart) &&
            $thisPeriodEnd->isAfter($otherPeriodEnd)
        ) {
            return false;
        }

        return true;
    }

    public function contains(self $timePeriod) : bool
    {
        return $this->start->isBeforeOrEqual($timePeriod->start()) && $this->end->isAfterOrEqual($timePeriod->end());
    }

    public function revert() : self
    {
        return new self($this->end(), $this->start());
    }

    public function merge(self $timePeriod) : self
    {
        if (!$this->overlaps($timePeriod) && !$this->abuts($timePeriod)) {
            throw new InvalidArgumentException("Can't merge not overlapping time periods.");
        }

        return new self(
            $this->start->isBeforeOrEqual($timePeriod->start)
                ? $this->start()
                : $timePeriod->start,
            $this->end->isAfterOrEqual($timePeriod->end)
                ? $this->end()
                : $timePeriod->end()
        );
    }

    public function abuts(self $timePeriod) : bool
    {
        $thisPeriodForward = $this->isBackward()
            ? $this->revert()
            : $this;

        $otherPeriodForward = $timePeriod->isBackward()
            ? $timePeriod->revert()
            : $timePeriod;

        if ($thisPeriodForward->end()->isEqual($otherPeriodForward->start())) {
            return true;
        }

        if ($thisPeriodForward->start()->isEqual($otherPeriodForward->end())) {
            return true;
        }

        return false;
    }
}
