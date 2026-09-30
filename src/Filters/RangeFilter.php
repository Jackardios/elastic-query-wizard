<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Concerns\ReadsNumbers;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\RangeQuery;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Range filter for numeric and date fields.
 *
 * Accepts only ES 9.x compatible operators: gt, gte, lt, lte.
 * Legacy operators (from, to, include_lower, include_upper) are NOT supported
 * as they were removed in Elasticsearch 9.x.
 *
 * @example filter[price][gte]=100&filter[price][lte]=500
 * @example filter[created_at][gte]=2024-01-01
 */
final class RangeFilter extends AbstractElasticFilter
{
    use HasParameters;
    use ReadsNumbers;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [RangeQuery::class];
    }

    /** @return array<string, string> */
    protected function reservedParameters(): array
    {
        return array_fill_keys(FilterValueSanitizer::RANGE_OPERATORS, 'the request');
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        if (FilterValueSanitizer::isBlank($value)) {
            return null;
        }

        $rangeFilters = FilterValueSanitizer::rangeFilterValue($value, $this, $this->readsNumbers);

        if (empty($rangeFilters)) {
            return null;
        }

        $query = Query::range($this->property);

        foreach ($rangeFilters as $filterName => $filterValue) {
            $query->{$filterName}($filterValue);
        }

        return $this->applyParametersOnQuery($query);
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
