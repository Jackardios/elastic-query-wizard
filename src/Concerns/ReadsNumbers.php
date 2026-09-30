<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Concerns;

use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;
use Jackardios\QueryWizard\Support\FilterValueParser;

/**
 * An opt-in rule that a filter's values are decimal numbers.
 *
 * @internal
 */
trait ReadsNumbers
{
    protected bool $readsNumbers = false;

    /**
     * Read each value as a decimal number (digits with an optional sign and
     * fraction, no exponent): text, a date or a boolean is a 400
     * (InvalidFilterValue) instead of a search that fails on a numeric field.
     */
    public function asNumber(): static
    {
        $this->readsNumbers = true;

        return $this;
    }

    /**
     * The values as numbers when asNumber() was called, as given otherwise.
     *
     * @template TValue
     *
     * @param  array<int, TValue>  $values
     * @return array<int, TValue>|array<int, int|float|string>
     *
     * @throws InvalidFilterValue For a value that is not a decimal number
     */
    protected function readNumbers(array $values): array
    {
        if (! $this->readsNumbers) {
            return $values;
        }

        $numbers = [];

        foreach ($values as $value) {
            $number = FilterValueParser::number($value, $this);

            if ($number !== null) {
                $numbers[] = $number;
            }
        }

        return $numbers;
    }
}
