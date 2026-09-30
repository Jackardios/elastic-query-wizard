<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\QueryWizard\Enums\SortDirection;

final class FieldSort extends AbstractElasticSort
{
    protected string|int|float|bool|null $missing = null;

    protected ?string $mode = null;

    protected ?string $unmappedType = null;

    /** @var array<string, mixed>|null */
    protected ?array $nested = null;

    protected ?string $numericType = null;

    protected ?string $format = null;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function missing(string|int|float|bool $value): static
    {
        $this->missing = $value;

        return $this;
    }

    public function missingFirst(): static
    {
        return $this->missing('_first');
    }

    public function missingLast(): static
    {
        return $this->missing('_last');
    }

    public function mode(string $mode): static
    {
        $this->mode = $mode;

        return $this;
    }

    public function unmappedType(string $unmappedType): static
    {
        $this->unmappedType = $unmappedType;

        return $this;
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
        $sort = Sort::field($this->property)->order($direction->value);

        if ($this->missing !== null) {
            $sort->missing($this->missing);
        }

        if ($this->mode !== null) {
            $sort->mode($this->mode);
        }

        if ($this->unmappedType !== null) {
            $sort->unmappedType($this->unmappedType);
        }

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
