<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Concerns;

use InvalidArgumentException;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

/**
 * An optional limit on the length of a text or pattern filter's value.
 *
 * @internal
 */
trait LimitsValueLength
{
    protected ?int $maxLength = null;

    /**
     * Answer a value longer than the given number of characters with a 400
     * (InvalidFilterValue); null removes the limit.
     *
     * @throws InvalidArgumentException When the length is not positive
     */
    public function maxLength(?int $length): static
    {
        if ($length !== null && $length < 1) {
            throw new InvalidArgumentException('The maximum length must be positive.');
        }

        $this->maxLength = $length;

        return $this;
    }

    /**
     * Elasticsearch measures text in UTF-16 code units, so a character outside
     * the Basic Multilingual Plane counts twice.
     *
     * @throws InvalidFilterValue
     */
    protected function assertValueLength(string $value): void
    {
        if ($this->maxLength === null) {
            return;
        }

        $length = intdiv(strlen((string) mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')), 2);

        if ($length > $this->maxLength) {
            throw InvalidFilterValue::make($value, $this, "Expected at most {$this->maxLength} characters, got {$length}.");
        }
    }
}
