<?php

namespace Jackardios\ElasticQueryWizard;

use ElasticAdapter\Search\SearchResponse;
use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;

abstract class ElasticInclude extends EloquentInclude
{
    protected SearchResponse $searchResponse;

    /**
     * @param ElasticQueryWizard $queryWizard
     * @param Builder $queryBuilder
     */
    abstract public function handle($queryWizard, Builder $queryBuilder): void;

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
