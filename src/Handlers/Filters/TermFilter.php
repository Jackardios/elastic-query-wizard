<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use ElasticScoutDriverPlus\Support\Query;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;

class TermFilter extends AbstractElasticFilter
{
    use HasParameters;

    public function handle($queryHandler, $queryBuilder, $value): void
    {
        if (!isset($value) || $value === '') {
            return;
        }

        $propertyName = $this->getPropertyName();

        $query = is_array($value)
            ? Query::terms()->field($propertyName)->values(array_values($value))
            : Query::term()->field($propertyName)->value($value);

        $query = $this->applyParametersOnQuery($query);

        $queryHandler->getFiltersBoolQuery()->must($query);
    }
}
