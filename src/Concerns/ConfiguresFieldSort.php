<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Concerns;

use Jackardios\EsScoutDriver\Sort\FieldSort;

/**
 * The options fieldSort() and nestedSort() share: missing values, the mode of
 * a multi-valued field and the type of an unmapped one.
 *
 * @internal
 */
trait ConfiguresFieldSort
{
    protected string|int|float|bool|null $missing = null;

    protected ?string $mode = null;

    protected ?string $unmappedType = null;

    /**
     * Value to use for documents missing the sort field.
     *
     * @param  string|int|float|bool  $value  Use '_first', '_last', or a specific value
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
     * Sort mode for multi-valued fields.
     *
     * @param  string  $mode  One of: 'min', 'max', 'avg', 'sum', 'median'
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

    protected function applyFieldSortOptions(FieldSort $sort): FieldSort
    {
        if ($this->missing !== null) {
            $sort->missing($this->missing);
        }

        if ($this->mode !== null) {
            $sort->mode($this->mode);
        }

        if ($this->unmappedType !== null) {
            $sort->unmappedType($this->unmappedType);
        }

        return $sort;
    }
}
