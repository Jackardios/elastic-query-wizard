<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\EsScoutDriver\Support\Query;
use stdClass;

/**
 * Random/shuffle sorting with optional seed for reproducibility.
 *
 * Wraps the query built so far, filters included, in a function_score whose
 * random_score replaces the relevance score, and sorts by that score. Apply
 * it after every scoring clause: a scoring clause added to the search builder
 * later adds its score to the random one.
 *
 * Without a seed every request gets a new order, so pages of one listing can
 * repeat or skip documents; pass a seed (e.g. a session ID) to paginate.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-function-score-query.html#function-random
 */
final class RandomSort extends AbstractElasticSort
{
    protected int|string|null $seed = null;

    protected ?string $field = null;

    public static function make(string $property = '_random', ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /**
     * Set seed for reproducible random order.
     *
     * @param  int|string  $seed  Numeric seed or string identifier (e.g., session ID)
     */
    public function seed(int|string $seed): static
    {
        $this->seed = $seed;

        return $this;
    }

    /**
     * Set field for per-document consistent randomization.
     *
     * Used only with a seed, which Elasticsearch 8 rejects without a field.
     * Defaults to '_seq_no'. Not '_id': fielddata on _id is disabled by default.
     *
     * @param  string  $field  '_seq_no' or a unique field with doc values
     */
    public function field(string $field): static
    {
        $this->field = $field;

        return $this;
    }

    public function handle(SearchBuilder $builder, string $direction): void
    {
        $functionScore = Query::functionScore($this->queryBuiltSoFar($builder))
            ->addFunction($this->buildRandomScoreFunction())
            ->boostMode('replace');

        $builder->clearBoolQuery()->query($functionScore);
        $builder->sort(Sort::score()->order($direction));
    }

    /**
     * The bool query and the query set on the builder, combined as the builder would run them.
     *
     * @return BoolQuery|array<string, mixed>|null
     */
    private function queryBuiltSoFar(SearchBuilder $builder): BoolQuery|array|null
    {
        $boolQuery = $builder->getBoolQuery();
        $query = $builder->getQuery();

        if ($boolQuery === null || ! $boolQuery->hasClauses()) {
            return $query;
        }

        $boolQuery = clone $boolQuery;

        if ($query !== null) {
            $boolQuery->addMust($query);
        }

        return $boolQuery;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRandomScoreFunction(): array
    {
        if ($this->seed === null) {
            return ['random_score' => new stdClass];
        }

        return ['random_score' => ['seed' => $this->seed, 'field' => $this->field ?? '_seq_no']];
    }
}
