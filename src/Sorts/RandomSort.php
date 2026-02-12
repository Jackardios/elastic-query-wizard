<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\EsScoutDriver\Support\Query;
use stdClass;

/**
 * Random/shuffle sorting with optional seed for reproducibility.
 *
 * Uses function_score with random_score to randomize results.
 * With a seed, the same order is returned for repeated queries.
 *
 * Note: Seeded random requires a field parameter in Elasticsearch 7.0+.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-function-score-query.html#function-random
 */
final class RandomSort extends AbstractElasticSort
{
    protected int|string|null $seed = null;

    protected ?string $field = null;

    public static function make(string $property = '_random', ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    /**
     * Set seed for reproducible random order.
     *
     * @param int|string $seed Numeric seed or string identifier (e.g., session ID)
     */
    public function seed(int|string $seed): static
    {
        $this->seed = $seed;
        return $this;
    }

    /**
     * Set field for per-document consistent randomization.
     *
     * Required for seeded random in Elasticsearch 7.0+.
     * Defaults to '_seq_no' if seed is set but field is not.
     *
     * @param string $field Usually '_seq_no', '_id', or a unique field
     */
    public function field(string $field): static
    {
        $this->field = $field;
        return $this;
    }

    public function getType(): string
    {
        return 'random';
    }

    public function handle(SearchBuilder $builder, string $direction): void
    {
        $randomScore = $this->buildRandomScoreFunction();

        $functionScore = Query::functionScore()
            ->addFunction($randomScore)
            ->boostMode('replace');

        $builder->must($functionScore);
        $builder->sort(Sort::score()->order($direction));
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildRandomScoreFunction(): array
    {
        if ($this->seed === null) {
            return ['random_score' => new stdClass()];
        }

        $randomScore = ['seed' => $this->seed];

        // Field is required for seeded random in ES 7.0+
        $randomScore['field'] = $this->field ?? '_seq_no';

        return ['random_score' => $randomScore];
    }
}
