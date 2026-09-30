<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\QueryWizard\Enums\SortDirection;

/**
 * Script-based sorting using Painless scripts.
 */
final class ScriptSort extends AbstractElasticSort
{
    protected string $scriptSource;

    protected string $type = 'number';

    /** @var array<string, mixed> */
    protected array $params = [];

    protected ?string $mode = null;

    /** @var array<string, mixed>|null */
    protected ?array $nested = null;

    protected function __construct(string $property, string $scriptSource, ?string $alias = null)
    {
        parent::__construct($property, $alias);
        $this->scriptSource = $scriptSource;
    }

    public static function make(string $property, string $scriptSource, ?string $alias = null): static
    {
        return new self($property, $scriptSource, $alias);
    }

    public function type(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function params(array $params): static
    {
        $this->params = $params;

        return $this;
    }

    public function mode(string $mode): static
    {
        $this->mode = $mode;

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

    public function handle(SearchBuilder $builder, SortDirection $direction): void
    {
        $script = ['source' => $this->scriptSource];

        if (! empty($this->params)) {
            $script['params'] = $this->params;
        }

        $sort = Sort::script($script, $this->type)->order($direction->value);

        if ($this->mode !== null) {
            $sort->mode($this->mode);
        }

        if ($this->nested !== null) {
            $sort->nested($this->nested);
        }

        $builder->sort($sort);
    }
}
