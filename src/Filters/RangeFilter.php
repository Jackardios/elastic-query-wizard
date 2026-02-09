<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

class RangeFilter extends AbstractElasticFilter
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

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        if (empty($value)) {
            return;
        }

        $propertyName = $this->property;
        $rangeFilters = FilterValueSanitizer::rangeFilterValue($value, $propertyName);

        if (empty($rangeFilters)) {
            return;
        }

        $query = Query::range($propertyName);

        foreach ($rangeFilters as $filterName => $filterValue) {
            $query->{$filterName}($filterValue);
        }

        $query = $this->applyParametersOnQuery($query);

        $builder->filter($query);
    }
}
