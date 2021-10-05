<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Jackardios\QueryWizard\Abstracts\Handlers\Filters\AbstractFilter;
use Jackardios\ElasticQueryWizard\Handlers\ElasticQueryHandler;

abstract class AbstractElasticFilter extends AbstractFilter
{
    /**
     * @param ElasticQueryHandler $queryHandler
     * @param SearchRequestBuilder $queryBuilder
     * @param mixed $value
     */
    abstract public function handle($queryHandler, $queryBuilder, $value): void;
}
