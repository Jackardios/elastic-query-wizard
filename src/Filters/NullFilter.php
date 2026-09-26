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
 * By default:
 * - Truthy value → field IS NULL (doesn't exist)
 * - Falsy value → field IS NOT NULL (exists)
 *
 * When invertLogic is true, the behavior is reversed.
 */
final class NullFilter extends AbstractElasticFilter
{
    use AddsExistsQuery;

    protected bool $invertLogic = false;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
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

        $shouldBeNull = $this->invertLogic ? ! $isTruthy : $isTruthy;

        $this->addExistsQuery($boolQuery, Query::exists($this->property), $shouldBeNull);
    }
}
