<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use ElasticScoutDriverPlus\Support\Query;

class MatchFilter extends AbstractParameterizedElasticFilter
{
    public function handle($queryHandler, $queryBuilder, $value): void
    {
        $propertyName = $this->getPropertyName();

        if (is_array($value)) {
            $value = implode(',', $value);
        }

        $query = Query::match()->field($propertyName)->query($value);
        $this->applyParametersOnQuery($query);

        $queryHandler->getMainBoolQuery()->must($query);
    }
}
