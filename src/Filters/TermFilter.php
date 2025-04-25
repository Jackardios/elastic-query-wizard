<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use Elastic\ScoutDriverPlus\Support\Query;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;

class TermFilter extends ElasticFilter
{
    use HasParameters;

    public function handle($queryWizard, $queryBuilder, $value): void
    {
        $prepared = is_array($value)
            ? FilterValueSanitizer::arrayWithOnlyFilledItems($value)
            : $value;

        if (FilterValueSanitizer::isBlank($prepared)) {
            return;
        }

        $propertyName = $this->getPropertyName();

        $query = is_array($prepared)
            ? Query::terms()->field($propertyName)->values($prepared)
            : Query::term()->field($propertyName)->value($prepared);

        $query = $this->applyParametersOnQuery($query);

        $queryWizard->getRootBoolQuery()->filter($query);
    }
}
