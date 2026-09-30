<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Closure;
use Jackardios\ElasticQueryWizard\Concerns\ConfiguresFieldSort;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\QueryWizard\Enums\SortDirection;

/**
 * Sort by a field within nested documents.
 *
 * Allows sorting by nested field values with optional filtering
 * to select which nested documents to consider.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/sort-search-results.html#nested-sorting
 */
final class NestedSort extends AbstractElasticSort
{
    use ConfiguresFieldSort;

    protected string $path;

    protected string $nestedField;

    /** @var QueryInterface|Closure(): QueryInterface|array<string, mixed>|null */
    protected QueryInterface|Closure|array|null $nestedFilter = null;

    protected ?int $maxChildren = null;

    protected function __construct(
        string $property,
        string $path,
        string $nestedField,
        ?string $alias = null
    ) {
        parent::__construct($property, $alias);
        $this->path = $path;
        $this->nestedField = $nestedField;
    }

    public static function make(
        string $property,
        string $path,
        string $nestedField,
        ?string $alias = null
    ): static {
        return new self($property, $path, $nestedField, $alias);
    }

    /**
     * Filter to select which nested documents to sort by.
     *
     * @param  QueryInterface|Closure(): QueryInterface|array<string, mixed>  $filter
     */
    public function nestedFilter(QueryInterface|Closure|array $filter): static
    {
        $this->nestedFilter = $filter;

        return $this;
    }

    /**
     * Maximum number of nested documents to consider.
     */
    public function maxChildren(int $maxChildren): static
    {
        $this->maxChildren = $maxChildren;

        return $this;
    }

    public function handle(SearchBuilder $builder, SortDirection $direction): void
    {
        $fullField = $this->path.'.'.$this->nestedField;

        $sort = $this->applyFieldSortOptions(Sort::field($fullField)->order($direction->value));
        $sort->nested($this->buildNestedConfig());

        $builder->sort($sort);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildNestedConfig(): array
    {
        $config = ['path' => $this->path];

        if ($this->nestedFilter !== null) {
            $filter = $this->nestedFilter instanceof Closure
                ? ($this->nestedFilter)()
                : $this->nestedFilter;

            $config['filter'] = $filter instanceof QueryInterface
                ? $filter->toArray()
                : $filter;
        }

        if ($this->maxChildren !== null) {
            $config['max_children'] = $this->maxChildren;
        }

        return $config;
    }
}
