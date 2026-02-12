<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\Specialized\MoreLikeThisQuery;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Find documents similar to provided text or documents.
 *
 * Useful for "related content", "more like this", or recommendation features.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-mlt-query.html
 */
final class MoreLikeThisFilter extends AbstractElasticFilter
{
    /** @var string[] */
    protected array $fields;

    protected ?int $minTermFreq = null;
    protected ?int $maxQueryTerms = null;
    protected ?int $minDocFreq = null;
    protected ?int $maxDocFreq = null;
    protected ?int $minWordLength = null;
    protected ?int $maxWordLength = null;
    protected ?string $analyzer = null;
    protected int|string|null $minimumShouldMatch = null;
    protected ?float $boost = null;
    protected ?bool $include = null;
    protected ?float $boostTerms = null;

    /**
     * @param string[] $fields Fields to analyze for similarity
     */
    protected function __construct(array $fields, string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);
        $this->fields = $fields;
    }

    /**
     * @param string[] $fields Fields to analyze for similarity
     */
    public static function make(array $fields, string $property, ?string $alias = null): static
    {
        return new static($fields, $property, $alias);
    }

    /**
     * Minimum term frequency in the source document.
     */
    public function minTermFreq(int $value): static
    {
        $this->minTermFreq = $value;
        return $this;
    }

    /**
     * Maximum number of query terms selected.
     */
    public function maxQueryTerms(int $value): static
    {
        $this->maxQueryTerms = $value;
        return $this;
    }

    /**
     * Minimum document frequency for terms.
     */
    public function minDocFreq(int $value): static
    {
        $this->minDocFreq = $value;
        return $this;
    }

    /**
     * Maximum document frequency for terms.
     */
    public function maxDocFreq(int $value): static
    {
        $this->maxDocFreq = $value;
        return $this;
    }

    /**
     * Minimum word length for terms.
     */
    public function minWordLength(int $value): static
    {
        $this->minWordLength = $value;
        return $this;
    }

    /**
     * Maximum word length for terms.
     */
    public function maxWordLength(int $value): static
    {
        $this->maxWordLength = $value;
        return $this;
    }

    /**
     * Analyzer for the query text.
     */
    public function analyzer(string $analyzer): static
    {
        $this->analyzer = $analyzer;
        return $this;
    }

    /**
     * Minimum number of terms that should match.
     *
     * @param int|string $value e.g., 2, '30%', '3<90%'
     */
    public function minimumShouldMatch(int|string $value): static
    {
        $this->minimumShouldMatch = $value;
        return $this;
    }

    /**
     * Query boost factor.
     */
    public function boost(float $boost): static
    {
        $this->boost = $boost;
        return $this;
    }

    /**
     * Include the input documents in the results.
     */
    public function include(bool $include = true): static
    {
        $this->include = $include;
        return $this;
    }

    /**
     * Boost factor for significant terms.
     */
    public function boostTerms(float $boostTerms): static
    {
        $this->boostTerms = $boostTerms;
        return $this;
    }

    public function getType(): string
    {
        return 'more_like_this';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        $like = $this->prepareLikeValue($value);

        if ($like === null) {
            return;
        }

        $query = Query::moreLikeThis($this->fields, $like);

        $this->applyParameters($query);

        $builder->must($query);
    }

    /**
     * @return string|array<int, string|array<string, mixed>>|null
     */
    protected function prepareLikeValue(mixed $value): string|array|null
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            return FilterValueSanitizer::isBlank($trimmed) ? null : $trimmed;
        }

        if (is_array($value)) {
            // Document reference: {_index, _id}
            if (isset($value['_index'], $value['_id'])) {
                return [$value];
            }

            // Array of mixed values
            $filtered = array_filter($value, fn($item) => !FilterValueSanitizer::isBlank($item));
            return $filtered === [] ? null : array_values($filtered);
        }

        return null;
    }

    protected function applyParameters(MoreLikeThisQuery $query): void
    {
        if ($this->minTermFreq !== null) {
            $query->minTermFreq($this->minTermFreq);
        }

        if ($this->maxQueryTerms !== null) {
            $query->maxQueryTerms($this->maxQueryTerms);
        }

        if ($this->minDocFreq !== null) {
            $query->minDocFreq($this->minDocFreq);
        }

        if ($this->maxDocFreq !== null) {
            $query->maxDocFreq($this->maxDocFreq);
        }

        if ($this->minWordLength !== null) {
            $query->minWordLength($this->minWordLength);
        }

        if ($this->maxWordLength !== null) {
            $query->maxWordLength($this->maxWordLength);
        }

        if ($this->analyzer !== null) {
            $query->analyzer($this->analyzer);
        }

        if ($this->minimumShouldMatch !== null) {
            $query->minimumShouldMatch($this->minimumShouldMatch);
        }

        if ($this->boost !== null) {
            $query->boost($this->boost);
        }

        if ($this->include !== null) {
            $query->include($this->include);
        }

        if ($this->boostTerms !== null) {
            $query->boostTerms($this->boostTerms);
        }
    }
}
