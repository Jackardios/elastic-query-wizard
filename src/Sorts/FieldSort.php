<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\ElasticQueryWizard\Concerns\ConfiguresFieldSort;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\QueryWizard\Enums\SortDirection;

final class FieldSort extends AbstractElasticSort
{
    use ConfiguresFieldSort;

    /** @var array<string, mixed>|null */
    protected ?array $nested = null;

    protected ?string $numericType = null;

    protected ?string $format = null;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /**
     * @param  array<string, mixed>  $nested
     */
    public function nested(array $nested): static
    {
        $this->nested = $nested;

        return $this;
    }

    public function numericType(string $numericType): static
    {
        $this->numericType = $numericType;

        return $this;
    }

    public function format(string $format): static
    {
        $this->format = $format;

        return $this;
    }

    public function handle(SearchBuilder $builder, SortDirection $direction): void
    {
        $sort = $this->applyFieldSortOptions(Sort::field($this->property)->order($direction->value));

        if ($this->nested !== null) {
            $sort->nested($this->nested);
        }

        if ($this->numericType !== null) {
            $sort->numericType($this->numericType);
        }

        if ($this->format !== null) {
            $sort->format($this->format);
        }

        $builder->sort($sort);
    }
}
