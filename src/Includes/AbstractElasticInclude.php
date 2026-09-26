<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Includes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jackardios\EsScoutDriver\Search\SearchResult;
use Jackardios\QueryWizard\Includes\AbstractInclude;

abstract class AbstractElasticInclude extends AbstractInclude
{
    protected ?SearchResult $searchResult = null;

    public function setSearchResult(SearchResult $searchResult): static
    {
        $this->searchResult = $searchResult;

        return $this;
    }

    public function getSearchResult(): ?SearchResult
    {
        return $this->searchResult;
    }

    /**
     * @param  Builder<Model>  $eloquentBuilder
     */
    abstract public function handleEloquent(Builder $eloquentBuilder): void;

    public function apply(mixed $subject): mixed
    {
        if ($subject instanceof Builder) {
            $this->handleEloquent($subject);
        }

        return $subject;
    }
}
