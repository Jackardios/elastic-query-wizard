<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Sorts;

use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Jackardios\QueryWizard\Abstracts\Handlers\Sorts\AbstractSort;
use Jackardios\ElasticQueryWizard\Handlers\ElasticQueryHandler;

abstract class AbstractElasticSort extends AbstractSort
{
    /**
     * @param ElasticQueryHandler $queryHandler
     * @param SearchRequestBuilder $queryBuilder
     * @param string $direction
     */
    abstract public function handle($queryHandler, $queryBuilder, string $direction): void;
}
