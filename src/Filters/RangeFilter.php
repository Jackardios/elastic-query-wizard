<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use Elastic\ScoutDriverPlus\Support\Query;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;

class RangeFilter extends ElasticFilter
{
    use HasParameters;

    public function handle($queryWizard, $queryBuilder, $value): void
    {
        if (empty($value)) {
            return;
        }

        $propertyName = $this->getPropertyName();
        $rangeFilters = FilterValueSanitizer::rangeFilterValue($value, $propertyName);
        $query = Query::range()->field($propertyName);

        foreach ($rangeFilters as $filterName => $filterValue) {
            $query->{$filterName}($filterValue);
        }

        $this->applyParametersOnQuery($query);

        $queryWizard->getRootBoolQuery()->must($query);
    }
}
