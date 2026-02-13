<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
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

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'range';
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        if (empty($value)) {
            return null;
        }

        $rangeFilters = FilterValueSanitizer::rangeFilterValue($value, $this->property);

        if (empty($rangeFilters)) {
            return null;
        }

        $query = Query::range($this->property);

        foreach ($rangeFilters as $filterName => $filterValue) {
            $query->{$filterName}($filterValue);
        }

        return $this->applyParametersOnQuery($query);
    }
}
