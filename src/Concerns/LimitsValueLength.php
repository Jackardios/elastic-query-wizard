<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Concerns;

use InvalidArgumentException;
use Jackardios\ElasticQueryWizard\Support\PackageConfig;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

/**
 * The limit on the length of a text or pattern filter's value.
 *
 * @internal
 */
trait LimitsValueLength
{
    /**
     * The filter's own default; null leaves it to the config.
     */
    protected ?int $maxLength = null;

    private bool $maxLengthIsSet = false;

    /**
     * Answer a value longer than the given number of characters with a 400
     * (InvalidFilterValue); null removes the limit. Without this call the
     * filter's own default applies, or `elastic-query-wizard.max_text_length`
     * (1000) for a filter that has none.
     *
     * @throws InvalidArgumentException When the length is not positive
     */
    public function maxLength(?int $length): static
    {
        if ($length !== null && $length < 1) {
            throw new InvalidArgumentException('The maximum length must be positive.');
        }

        $this->maxLength = $length;
        $this->maxLengthIsSet = true;

        return $this;
    }

    /**
     * The limit of a filter that sets none: `elastic-query-wizard.max_text_length`.
     *
     * @throws InvalidArgumentException When the config value is not a positive integer or null
     */
    protected function defaultMaxLength(): ?int
    {
        return PackageConfig::positiveIntOrNull('max_text_length', 1000);
    }

    /**
     * Elasticsearch measures text in UTF-16 code units, so a character outside
     * the Basic Multilingual Plane counts twice.
     *
     * @throws InvalidFilterValue
     * @throws InvalidArgumentException From defaultMaxLength()
     */
    protected function assertValueLength(string $value): void
    {
        $maxLength = $this->maxLengthIsSet
            ? $this->maxLength
            : ($this->maxLength ?? $this->defaultMaxLength());

        if ($maxLength === null) {
            return;
        }

        $length = intdiv(strlen((string) mb_convert_encoding($value, 'UTF-16LE', 'UTF-8')), 2);

        if ($length > $maxLength) {
            throw InvalidFilterValue::make($value, $this, "Expected at most {$maxLength} characters, got {$length}.");
        }
    }
}
