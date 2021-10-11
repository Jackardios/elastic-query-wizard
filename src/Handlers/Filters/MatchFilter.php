<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use ElasticScoutDriverPlus\Support\Query;

class MatchFilter extends AbstractParameterizedElasticFilter
{
    public function handle($queryHandler, $queryBuilder, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (is_array($value)) {
            $value = implode(',', $value);
        }

        $propertyName = $this->getPropertyName();

        $query = Query::match()->field($propertyName)->query($value);
        $this->applyParametersOnQuery($query);

        $queryHandler->getMainBoolQuery()->must($query);
    }
}
