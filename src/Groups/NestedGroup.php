<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Groups;

use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Groups child filters into a nested query.
 *
 * Use this when filtering on nested document fields where multiple
 * conditions must match within the same nested document.
 *
 * @example
 * ElasticGroup::nested('sides')
 *     ->inFilter()
 *     ->children([
 *         ElasticFilter::term(field: 'sides.id', key: 'id')->inFilter(),
 *         ElasticFilter::multiMatch(fields: ['sides.address'], key: 'search')->inMust(),
 *     ])
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-nested-query.html
 */
final class NestedGroup extends AbstractElasticGroup
{
    protected string $path;

    protected ?string $scoreMode = null;

    protected ?bool $ignoreUnmapped = null;

    protected function __construct(string $path, ?string $alias = null)
    {
        parent::__construct($path, $alias);
        $this->path = $path;
    }

    public static function make(string $path, ?string $alias = null): static
    {
        return new static($path, $alias);
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

    /**
     * Get the nested path.
     */
    public function getPath(): string
    {
        return $this->path;
    }

    public function getType(): string
    {
        return 'nested_group';
    }

    public function buildGroupQuery(array $childValues): ?QueryInterface
    {
        if (empty($childValues) || empty($this->children)) {
            return null;
        }

        $innerBoolQuery = new BoolQuery();

        $this->applyChildrenToQuery($innerBoolQuery, $childValues);

        if ($innerBoolQuery->isEmpty()) {
            return null;
        }

        $nestedQuery = Query::nested($this->path, $innerBoolQuery);

        if ($this->scoreMode !== null) {
            $nestedQuery->scoreMode($this->scoreMode);
        }

        if ($this->ignoreUnmapped !== null) {
            $nestedQuery->ignoreUnmapped($this->ignoreUnmapped);
        }

        return $nestedQuery;
    }
}
