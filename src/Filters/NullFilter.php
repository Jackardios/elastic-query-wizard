<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\AddsExistsQuery;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Support\FilterValueParser;

/**
 * Filter by NULL/NOT NULL values (field existence in Elasticsearch).
 *
 * make() ("is null"):
 * - true → field IS NULL (doesn't exist)
 * - false → field IS NOT NULL (exists)
 *
 * notNull() ("is not null") reverses both.
 */
final class NullFilter extends AbstractElasticFilter
{
    use AddsExistsQuery;

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

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrBlankValueShape($value);
    }

    /**
     * This filter has conditional clause logic (filter vs must_not) so we
     * implement buildQuery to return null, and override handle() for
     * the conditional clause logic.
     */
    public function buildQuery(mixed $value): ?QueryInterface
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
        $isTruthy = FilterValueParser::boolean($value, $this);

        if ($isTruthy === null) {
            return;
        }

        $shouldBeNull = $this->matchesNotNull ? ! $isTruthy : $isTruthy;

        $this->addExistsQuery($boolQuery, Query::exists($this->property), $shouldBeNull);
    }
}
