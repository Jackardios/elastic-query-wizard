<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use ElasticScoutDriverPlus\Support\Query;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;

class MatchFilter extends AbstractElasticFilter
{
    use HasParameters;

    public function handle($queryHandler, $queryBuilder, $value): void
    {
        if (!isset($value) || $value === '') {
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
