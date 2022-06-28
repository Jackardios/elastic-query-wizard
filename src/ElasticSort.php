<?php

namespace Jackardios\ElasticQueryWizard;

use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Jackardios\QueryWizard\Abstracts\AbstractSort;

abstract class ElasticSort extends AbstractSort
{
    /**
     * @param ElasticQueryWizard $queryWizard
     * @param SearchRequestBuilder $queryBuilder
     * @param string $direction
     */
    abstract public function handle($queryWizard, SearchRequestBuilder $queryBuilder, string $direction): void;
}
