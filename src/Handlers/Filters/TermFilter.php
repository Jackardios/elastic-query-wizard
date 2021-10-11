<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use ElasticScoutDriverPlus\Support\Query;

class TermFilter extends AbstractParameterizedElasticFilter
{
    public function handle($queryHandler, $queryBuilder, $value): void
    {
        if (empty($value)) {
            return;
        }
        
        $propertyName = $this->getPropertyName();

        $query = is_array($value)
            ? Query::terms()->field($propertyName)->values(array_values($value))
            : Query::term()->field($propertyName)->value($value);
        $this->applyParametersOnQuery($query);

        $queryHandler->getMainBoolQuery()->must($query);
    }
}
