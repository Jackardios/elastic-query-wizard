<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use ElasticScoutDriverPlus\Support\Query;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidRangeValue;

class RangeFilter extends ElasticFilter
{
    use HasParameters;

    public function handle($queryWizard, $queryBuilder, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (! is_array($value)) {
            throw InvalidRangeValue::make($this->getName());
        }

        $propertyName = $this->getPropertyName();
        $query = Query::range()->field($propertyName);

        foreach ($value as $itemKey => $itemValue) {
            if (! in_array($itemKey, ['gt', 'gte', 'lt', 'lte'])) {
                throw InvalidRangeValue::make($this->getName());
            }

            $query->{$itemKey}($itemValue);
        }

        $this->applyParametersOnQuery($query);

        $queryWizard->must($query);
    }
}
