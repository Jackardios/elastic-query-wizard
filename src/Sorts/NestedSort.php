<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Closure;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;

/**
 * Sort by a field within nested documents.
 *
 * Allows sorting by nested field values with optional filtering
 * to select which nested documents to consider.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/sort-search-results.html#nested-sorting
 */
class NestedSort extends AbstractElasticSort
{
    protected string $path;
    protected string $nestedField;

    protected string|int|float|bool|null $missing = null;
    protected ?string $mode = null;
    protected ?string $unmappedType = null;

    /** @var QueryInterface|Closure(): QueryInterface|array<string, mixed>|null */
    protected QueryInterface|Closure|array|null $nestedFilter = null;

    protected ?int $maxChildren = null;

    protected function __construct(
        string $path,
        string $nestedField,
        string $property,
        ?string $alias = null
    ) {
        parent::__construct($property, $alias);
        $this->path = $path;
        $this->nestedField = $nestedField;
    }

    public static function make(
        string $path,
        string $nestedField,
        string $property,
        ?string $alias = null
    ): static {
        return new static($path, $nestedField, $property, $alias);
    }

    /**
     * Value to use for documents missing the sort field.
     *
     * @param string|int|float|bool $value Use '_first', '_last', or a specific value
     */
    public function missing(string|int|float|bool $value): static
    {
        $this->missing = $value;
        return $this;
    }

    /**
     * Sort missing values first.
     */
    public function missingFirst(): static
    {
        return $this->missing('_first');
    }

    /**
     * Sort missing values last.
     */
    public function missingLast(): static
    {
        return $this->missing('_last');
    }

    /**
     * Sort mode for multi-valued nested fields.
     *
     * @param string $mode One of: 'min', 'max', 'avg', 'sum', 'median'
     */
    public function mode(string $mode): static
    {
        $this->mode = $mode;
        return $this;
    }

    /**
     * Type to use when the sort field is unmapped.
     */
    public function unmappedType(string $type): static
    {
        $this->unmappedType = $type;
        return $this;
    }

    /**
     * Filter to select which nested documents to sort by.
     *
     * @param QueryInterface|Closure(): QueryInterface|array<string, mixed> $filter
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

    public function getType(): string
    {
        return 'nested';
    }

    public function handle(SearchBuilder $builder, string $direction): void
    {
        $fullField = $this->path . '.' . $this->nestedField;

        $sort = Sort::field($fullField)->order($direction);

        if ($this->missing !== null) {
            $sort->missing($this->missing);
        }

        if ($this->mode !== null) {
            $sort->mode($this->mode);
        }

        if ($this->unmappedType !== null) {
            $sort->unmappedType($this->unmappedType);
        }

        $nestedConfig = $this->buildNestedConfig();
        $sort->nested($nestedConfig);

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
