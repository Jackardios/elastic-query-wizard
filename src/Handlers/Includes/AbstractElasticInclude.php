<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Includes;

use ElasticAdapter\Search\SearchResponse;
use Illuminate\Database\Eloquent\Builder;
use Jackardios\ElasticQueryWizard\Handlers\ElasticQueryHandler;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\AbstractEloquentInclude;

abstract class AbstractElasticInclude extends AbstractEloquentInclude
{
    protected SearchResponse $searchResponse;

    /**
     * @param ElasticQueryHandler $queryHandler
     * @param Builder $queryBuilder
     */
    abstract public function handle($queryHandler, $queryBuilder): void;

    /**
     * @param SearchResponse $searchResponse
     * @return $this
     */
    public function setSearchResponse(SearchResponse $searchResponse): self
    {
        $this->searchResponse = $searchResponse;

        return $this;
    }

    public function getSearchResponse(): SearchResponse
    {
        return $this->searchResponse;
    }
}
