<?php

declare(strict_types=1);

namespace Ocelot\Calendar\Gregorian\Holidays;

use Ocelot\Calendar\Gregorian\Day;

/**
 * @psalm-immutable
 */
final class Holiday
{
    private Day $day;

    private HolidayName $name;

    public function __construct(Day $day, HolidayName $name)
    {
        $this->day = $day;
        $this->name = $name;
    }

    public function day(): Day
    {
        return $this->day;
    }

    public function name(?string $locale = null): string
    {
        return $this->name->name($locale);
    }

    /**
     * @return array<int, string>
     */
    public function locales() : array
    {
        return $this->name->locales();
    }
}