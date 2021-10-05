<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use ElasticScoutDriverPlus\Support\Query;

class TermFilter extends AbstractElasticFilter
{
    public function handle($queryHandler, $queryBuilder, $value): void
    {
        $propertyName = $this->getPropertyName();

        $queryHandler->getMainBoolQuery()->must(
            is_array($value)
                ? Query::terms()->field($propertyName)->values(array_values($value))
                : Query::term()->field($propertyName)->value($value)
        );
    }
}
