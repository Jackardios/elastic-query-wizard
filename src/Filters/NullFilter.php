<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Filter by NULL/NOT NULL values (field existence in Elasticsearch).
 *
 * By default:
 * - Truthy value → field IS NULL (doesn't exist)
 * - Falsy value → field IS NOT NULL (exists)
 *
 * When invertLogic is true, the behavior is reversed.
 */
final class NullFilter extends AbstractElasticFilter
{
    protected bool $invertLogic = false;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    /**
     * Invert the filter logic.
     * When inverted: truthy → NOT NULL, falsy → NULL
     */
    public function withInvertedLogic(): static
    {
        $this->invertLogic = true;

        return $this;
    }

    /**
     * Use normal filter logic (default).
     * Normal: truthy → NULL, falsy → NOT NULL
     */
    public function withoutInvertedLogic(): static
    {
        $this->invertLogic = false;

        return $this;
    }

    public function getType(): string
    {
        return 'null';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        if (FilterValueSanitizer::isBlank($value)) {
            return;
        }

        $isTruthy = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($isTruthy === null) {
            return;
        }

        $query = Query::exists($this->property);

        $shouldBeNull = $this->invertLogic ? ! $isTruthy : $isTruthy;

        if ($shouldBeNull) {
            $builder->mustNot($query);
        } else {
            $builder->filter($query);
        }
    }
}
