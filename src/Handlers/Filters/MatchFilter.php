<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use ElasticScoutDriverPlus\Support\Query;

class MatchFilter extends AbstractElasticFilter
{
    public function handle($queryHandler, $queryBuilder, $value): void
    {
        $propertyName = $this->getPropertyName();

        if (is_array($value)) {
            $value = implode(',', $value);
        }

        $queryHandler->getFiltersBoolQuery()->must(
            Query::match()->field($propertyName)->query($value)
        );
    }
}
