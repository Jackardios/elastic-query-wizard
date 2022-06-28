<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use ElasticScoutDriverPlus\Support\Query;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;

class MatchFilter extends ElasticFilter
{
    use HasParameters;

    public function handle($queryWizard, $queryBuilder, $value): void
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

        $queryWizard->must($query);
    }
}
