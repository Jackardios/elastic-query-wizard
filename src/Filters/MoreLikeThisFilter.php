<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\LimitsValueLength;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Specialized\MoreLikeThisQuery;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

/**
 * Finds documents like the given texts or documents of the searched index, for
 * "related content" and recommendations.
 *
 * The value is one text, a list of texts, or `_id` references to documents
 * (`filter[similar][_id]=5`, `filter[similar][][_id]=5`). A reference takes
 * only an `_id`: the document is read from the index being searched.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-mlt-query.html
 */
final class MoreLikeThisFilter extends AbstractElasticFilter
{
    use LimitsValueLength;

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

    private const EXPECTED = 'Expected a text, a list of texts or `_id` references.';

    /**
     * A text is one value, which may contain the separator; several texts
     * come as a list.
     *
     * @param  string[]  $fields  Fields to analyze for similarity
     */
    protected function __construct(array $fields, string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);
        $this->fields = $fields;

        $this->withoutValueSplitting();
        $this->withStructuredInput();
    }

    /**
     * @param  string[]  $fields  Fields to analyze for similarity
     */
    public static function make(array $fields, string $property, ?string $alias = null): static
    {
        return new self($fields, $property, $alias);
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
     * @param  int|string  $value  e.g., 2, '30%', '3<90%'
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

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::Must;
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        $like = $this->prepareLikeValue($value);

        if ($like === null) {
            return null;
        }

        $query = Query::moreLikeThis($this->fields, $like);

        $this->applyParameters($query);

        return $query;
    }

    /**
     * @return string|list<string|array{_id: string}>|null
     *
     * @throws InvalidFilterValue
     */
    protected function prepareLikeValue(mixed $value): string|array|null
    {
        if (FilterValueSanitizer::isBlank($value)) {
            return null;
        }

        if (! is_array($value)) {
            return $this->likeText($value) ?? throw InvalidFilterValue::make($value, $this, self::EXPECTED);
        }

        if (! array_is_list($value) && array_filter(array_keys($value), is_string(...)) !== []) {
            return [$this->documentReference($value, $value)];
        }

        $like = [];

        foreach (array_values($value) as $item) {
            if (FilterValueSanitizer::isBlank($item)) {
                continue;
            }

            $like[] = is_array($item)
                ? $this->documentReference($item, $value)
                : ($this->likeText($item) ?? throw InvalidFilterValue::make($value, $this, self::EXPECTED));
        }

        return $like;
    }

    private function likeText(mixed $item): ?string
    {
        if (! is_string($item) && ! is_int($item) && ! is_float($item)) {
            return null;
        }

        $text = trim((string) $item);
        $this->assertValueLength($text);

        return $text;
    }

    /**
     * @param  array<mixed>  $reference
     * @return array{_id: string}
     *
     * @throws InvalidFilterValue
     */
    private function documentReference(array $reference, mixed $value): array
    {
        $id = $reference['_id'] ?? null;

        if (array_keys($reference) !== ['_id'] || ! (is_string($id) || is_int($id)) || trim((string) $id) === '') {
            throw InvalidFilterValue::make($value, $this, 'A document reference takes only an `_id`.');
        }

        return ['_id' => trim((string) $id)];
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

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
