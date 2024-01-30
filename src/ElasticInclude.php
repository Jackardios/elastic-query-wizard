<?php

namespace Jackardios\ElasticQueryWizard;

use Elastic\Adapter\Search\SearchResult;
use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Eloquent\EloquentInclude;

abstract class ElasticInclude extends EloquentInclude
{
    protected SearchResult $searchResult;

    /**
     * @param ElasticQueryWizard $queryWizard
     * @param Builder $queryBuilder
     */
    abstract public function handle($queryWizard, Builder $queryBuilder): void;

    /**
     * @param SearchResult $searchResult
     * @return $this
     */
    public function setSearchResult(SearchResult $searchResult): self
    {
        $this->searchResult = $searchResult;

        return $this;
    }

    public function getSearchResult(): SearchResult
    {
        return $this->searchResult;
    }
}
