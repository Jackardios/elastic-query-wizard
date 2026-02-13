<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
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

    /**
     * This filter has conditional clause logic (filter vs must_not) so we
     * implement buildQuery to return null, and override handle() for
     * the conditional clause logic.
     */
    public function buildQuery(mixed $value): QueryInterface|array|null
    {
        // buildQuery returns null because this filter has conditional clause logic
        // that requires handle() to decide between filter and must_not
        return null;
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        $this->applyNullLogic($builder->boolQuery(), $value);
    }

    public function handleInGroup(BoolQuery $innerBoolQuery, mixed $value): void
    {
        $this->applyNullLogic($innerBoolQuery, $value);
    }

    protected function applyNullLogic(BoolQuery $boolQuery, mixed $value): void
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
            // Field should be NULL (not exist) -> use must_not with exists query
            $boolQuery->addMustNot($query);
        } else {
            // Field should NOT be NULL (must exist) -> use effective clause with exists query
            $this->addQueryToBuilder($boolQuery, $query);
        }
    }
}
