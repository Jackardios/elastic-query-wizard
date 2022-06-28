<?php

namespace Jackardios\ElasticQueryWizard;

use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Jackardios\QueryWizard\Abstracts\AbstractFilter;

abstract class ElasticFilter extends AbstractFilter
{
    /**
     * @param ElasticQueryWizard $queryWizard
     * @param SearchRequestBuilder $queryBuilder
     * @param mixed $value
     */
    abstract public function handle($queryWizard, SearchRequestBuilder $queryBuilder, $value): void;
}
