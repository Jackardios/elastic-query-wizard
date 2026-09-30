<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

/**
 * Filter by NULL/NOT NULL values (field existence in Elasticsearch).
 *
 * make() ("is null"):
 * - true → field IS NULL (doesn't exist)
 * - false → field IS NOT NULL (exists)
 *
 * notNull() ("is not null") reverses both.
 */
final class NullFilter extends AbstractExistsFilter
{
    private bool $matchesNotNull = false;

    /**
     * Create a filter where true matches a missing field and false an existing one.
     */
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /**
     * Create a filter where true matches an existing field and false a missing one.
     */
    public static function notNull(string $property, ?string $alias = null): static
    {
        $filter = new self($property, $alias);
        $filter->matchesNotNull = true;

        return $filter;
    }

    protected function matchesMissingField(bool $value): bool
    {
        return $this->matchesNotNull ? ! $value : $value;
    }
}
