<?php

namespace Jackardios\ElasticQueryWizard;

use Elastic\ScoutDriverPlus\Builders\SearchParametersBuilder;
use Jackardios\QueryWizard\Abstracts\AbstractSort;

abstract class ElasticSort extends AbstractSort
{
    /**
     * @param ElasticQueryWizard $queryWizard
     * @param SearchParametersBuilder $queryBuilder
     * @param string $direction
     */
    abstract public function handle($queryWizard, SearchParametersBuilder $queryBuilder, string $direction): void;
}
