<?php

declare(strict_types=1);

namespace Aeon\Calendar\Gregorian;

/**
 * @psalm-immutable
 * @implements \IteratorAggregate<TimePeriod>
 */
final class TimePeriods implements \Countable, \IteratorAggregate
{
    /**
     * @var array<TimePeriod>
     */
    private array $periods;

    public function __construct(TimePeriod ...$periods)
    {
        $this->periods = $periods;
    }

    /**
     * @return array<TimePeriod>
     */
    public function all() : array
    {
        return $this->periods;
    }

    /**
     * @psalm-param pure-callable(TimePeriod $timePeriod) : void $iterator
     *
     * @param callable(TimePeriod $timePeriod) : void $iterator
     */
    public function each(callable $iterator) : void
    {
        foreach ($this->periods as $period) {
            $iterator($period);
        }
    }

    /**
     * @psalm-param pure-callable(TimePeriod $timePeriod) : mixed $iterator
     *
     * @param callable(TimePeriod $timePeriod) : mixed $iterator
     *
     * @return array<mixed>
     */
    public function map(callable $iterator) : array
    {
        return \array_map($iterator, $this->all());
    }

    /**
     * @psalm-param pure-callable(TimePeriod $timePeriod) : bool $iterator
     *
     * @param callable(TimePeriod $timePeriod) : bool $iterator
     *
     * @return self
     */
    public function filter(callable $iterator) : self
    {
        return new self(...\array_filter($this->all(), $iterator));
    }

    public function count() : int
    {
        return \count($this->all());
    }

    public function getIterator() : \Traversable
    {
        return new \ArrayIterator($this->all());
    }

    /**
     * Find all gaps between time periods.
     */
    public function gaps() : self
    {
        $periods = \array_map(
            function (TimePeriod $timePeriod) : TimePeriod {
                return $timePeriod->isBackward() ? $timePeriod->revert() : $timePeriod;
            },
            $this->sort()->all()
        );

        $gaps = [];
        $totalPeriod = \current($periods);

        while ($period = \next($periods)) {
            if ($totalPeriod->overlaps($period) || $totalPeriod->abuts($period)) {
                $totalPeriod = $totalPeriod->merge($period);
            } else {
                $gaps[] = new TimePeriod($totalPeriod->end(), $period->start());
                $totalPeriod = $period;
            }
        }

        return new self(...$gaps);
    }

    public function sort() : self
    {
        return $this->sortBy(TimePeriodsSort::asc());
    }

    public function sortBy(TimePeriodsSort $sort) : self
    {
        $periods = $this->all();

        \uasort(
            $periods,
            function (TimePeriod $timePeriodA, TimePeriod $timePeriodB) use ($sort) : int {
                if ($sort->byStartDate()) {
                    return $sort->isAscending()
                        ? $timePeriodA->start()->toDateTimeImmutable() <=> $timePeriodB->start()->toDateTimeImmutable()
                        : $timePeriodB->start()->toDateTimeImmutable() <=> $timePeriodA->start()->toDateTimeImmutable();
                }

                return $sort->isAscending()
                    ? $timePeriodA->end()->toDateTimeImmutable() <=> $timePeriodB->end()->toDateTimeImmutable()
                    : $timePeriodB->end()->toDateTimeImmutable() <=> $timePeriodA->end()->toDateTimeImmutable();
            }
        );

        return new self(...$periods);
    }

    public function first() : ?TimePeriod
    {
        $periods = $this->all();

        if (!\count($periods)) {
            return null;
        }

        return \current($periods);
    }

    public function last() : ?TimePeriod
    {
        $periods = $this->all();

        if (!\count($periods)) {
            return null;
        }

        return \end($periods);
    }

    public function add(TimePeriod ...$timePeriods) : self
    {
        return new self(...\array_merge($this->periods, $timePeriods));
    }

    public function merge(self $timePeriods) : self
    {
        return new self(...\array_merge($this->periods, $timePeriods->periods));
    }
}
