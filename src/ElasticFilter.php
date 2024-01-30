<?php

namespace Jackardios\ElasticQueryWizard;

use Elastic\ScoutDriverPlus\Builders\SearchParametersBuilder;
use Jackardios\QueryWizard\Abstracts\AbstractFilter;

abstract class ElasticFilter extends AbstractFilter
{
    /**
     * @param ElasticQueryWizard $queryWizard
     * @param SearchParametersBuilder $queryBuilder
     * @param mixed $value
     */
    abstract public function handle($queryWizard, SearchParametersBuilder $queryBuilder, $value): void;
}
