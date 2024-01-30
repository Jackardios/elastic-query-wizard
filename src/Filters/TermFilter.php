<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use Elastic\ScoutDriverPlus\Support\Query;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;

class TermFilter extends ElasticFilter
{
    use HasParameters;

    public function handle($queryWizard, $queryBuilder, $value): void
    {
        if (!isset($value) || $value === '') {
            return;
        }

        $propertyName = $this->getPropertyName();

        $query = is_array($value)
            ? Query::terms()->field($propertyName)->values(array_values($value))
            : Query::term()->field($propertyName)->value($value);

        $query = $this->applyParametersOnQuery($query);

        $queryWizard->getRootBoolQuery()->filter($query);
    }
}
