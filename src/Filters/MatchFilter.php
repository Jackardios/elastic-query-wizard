<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use Elastic\ScoutDriverPlus\Support\Query;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;

class MatchFilter extends ElasticFilter
{
    use HasParameters;

    public function handle($queryWizard, $queryBuilder, $value): void
    {
        if (is_array($value)) {
            $value = FilterValueSanitizer::arrayToCommaSeparatedString($value);
        }

        if (FilterValueSanitizer::isBlank($value)) {
            return;
        }

        $propertyName = $this->getPropertyName();

        $query = Query::match()->field($propertyName)->query($value);
        $this->applyParametersOnQuery($query);

        $queryWizard->getRootBoolQuery()->must($query);
    }
}
