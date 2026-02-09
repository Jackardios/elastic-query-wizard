<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Concerns;

use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use ReflectionClass;

trait AssertsElasticQuery
{
    protected function getFilterQueries(BoolQuery $boolQuery): array
    {
        return $this->clausesToArray($boolQuery->getFilterClauses());
    }

    protected function getMustQueries(BoolQuery $boolQuery): array
    {
        return $this->clausesToArray($boolQuery->getMustClauses());
    }

    protected function getMustNotQueries(BoolQuery $boolQuery): array
    {
        return $this->clausesToArray($boolQuery->getMustNotClauses());
    }

    protected function getShouldQueries(BoolQuery $boolQuery): array
    {
        return $this->clausesToArray($boolQuery->getShouldClauses());
    }

    protected function getSorts(SearchBuilder $builder): array
    {
        return $builder->getSort();
    }

    /**
     * @param array<int|string, QueryInterface|array> $clauses
     * @return array
     */
    private function clausesToArray(array $clauses): array
    {
        return array_values(array_map(
            fn ($query) => $query instanceof QueryInterface ? $query->toArray() : $query,
            $clauses
        ));
    }
}
