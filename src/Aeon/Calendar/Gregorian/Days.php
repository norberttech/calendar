<?php

declare(strict_types=1);

namespace Aeon\Calendar\Gregorian;

use Aeon\Calendar\DateTimeIterator;

/**
 * @psalm-immutable
 * @implements \IteratorAggregate<Day>
 */
final class Days implements \Countable, \IteratorAggregate
{
    /**
     * @var \Iterator<Day>
     */
    private \Iterator $days;

    /**
     * @param \Iterator<Day> $days
     */
    private function __construct(\Iterator $days)
    {
        $this->days = $days;
    }

    /**
     * @psalm-pure
     */
    public static function fromArray(Day ...$days) : self
    {
        /** @psalm-suppress ImpureMethodCall */
        return new self(new \ArrayIterator($days));
    }

    /**
     * @psalm-pure
     * @phpstan-ignore-next-line
     */
    public static function fromDateTimeIterator(DateTimeIterator $iterator) : self
    {
        /** @psalm-suppress ImpureMethodCall */
        return new self(DaysIterator::fromDateTimeIterator($iterator));
    }

    /**
     * @return array<Day>
     */
    public function all() : array
    {
        return \iterator_to_array($this->days);
    }

    /**
     * @psalm-template MapResultType
     *
     * @psalm-param pure-callable(Day $day) : MapResultType $iterator
     *
     * @param callable(Day $day) : MapResultType $iterator
     *
     * @return array<MapResultType>
     */
    public function map(callable $iterator) : array
    {
        return \array_map($iterator, $this->all());
    }

    /**
     * @psalm-param pure-callable(Day $day) : bool $iterator
     *
     * @param callable(Day $day) : bool $iterator
     */
    public function filter(callable $iterator) : self
    {
        return new self(new \CallbackFilterIterator($this->days, $iterator));
    }

    public function count() : int
    {
        return \iterator_count($this->days);
    }

    public function getIterator() : \Traversable
    {
        return $this->days;
    }
}
