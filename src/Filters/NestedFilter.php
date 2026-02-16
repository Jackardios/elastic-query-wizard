<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Closure;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Filter for nested documents.
 *
 * Nested documents are indexed as separate hidden documents and require
 * special query handling. This filter wraps inner queries in a nested query.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-nested-query.html
 */
final class NestedFilter extends AbstractElasticFilter
{
    protected string $path;

    /** @var QueryInterface|Closure(mixed): QueryInterface|array<string, mixed>|null */
    protected QueryInterface|Closure|array|null $innerQuery = null;

    protected ?string $scoreMode = null;

    protected ?bool $ignoreUnmapped = null;

    protected function __construct(string $path, string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);
        $this->path = $path;
    }

    public static function make(string $path, string $property, ?string $alias = null): static
    {
        return new static($path, $property, $alias);
    }

    /**
     * Set a custom inner query builder.
     *
     * If not set, creates a term/terms query on "{path}.{property}".
     *
     * @param QueryInterface|Closure(mixed): QueryInterface|array<string, mixed> $query
     */
    public function innerQuery(QueryInterface|Closure|array $query): static
    {
        $this->innerQuery = $query;
        return $this;
    }

    /**
     * Set the score mode for nested hits aggregation.
     *
     * @param string $mode One of: 'avg', 'max', 'min', 'sum', 'none'
     */
    public function scoreMode(string $mode): static
    {
        $this->scoreMode = $mode;
        return $this;
    }

    /**
     * Ignore the query if the nested path is unmapped.
     */
    public function ignoreUnmapped(bool $ignore = true): static
    {
        $this->ignoreUnmapped = $ignore;
        return $this;
    }

    public function getType(): string
    {
        return 'nested';
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        if (FilterValueSanitizer::isBlank($value)) {
            return null;
        }

        $innerQuery = $this->buildInnerQuery($value);

        if ($innerQuery === null) {
            return null;
        }

        $query = Query::nested($this->path, $innerQuery);

        if ($this->scoreMode !== null) {
            $query->scoreMode($this->scoreMode);
        }

        if ($this->ignoreUnmapped !== null) {
            $query->ignoreUnmapped($this->ignoreUnmapped);
        }

        return $query;
    }

    /**
     * @return QueryInterface|array<string, mixed>|null
     */
    protected function buildInnerQuery(mixed $value): QueryInterface|array|null
    {
        if ($this->innerQuery !== null) {
            return $this->innerQuery instanceof Closure
                ? ($this->innerQuery)($value)
                : $this->innerQuery;
        }

        $nestedField = $this->path . '.' . $this->property;
        $prepared = FilterValueSanitizer::toScalarArray($value);

        if ($prepared === []) {
            return null;
        }

        return count($prepared) === 1
            ? Query::term($nestedField, $prepared[0])
            : Query::terms($nestedField, $prepared);
    }
}
