<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;

/**
 * Script-based sorting using Painless scripts.
 *
 * ES 9.x compatibility note:
 * If using random_score in function_score queries, note that the default field
 * changed from `_id` to `_seq_no` in ES 9.x. Specify the field explicitly
 * for consistent behavior across ES versions.
 */
final class ScriptSort extends AbstractElasticSort
{
    protected string $scriptSource;
    protected string $type = 'number';
    protected array $params = [];
    protected ?string $mode = null;

    /** @var array<string, mixed>|null */
    protected ?array $nested = null;

    protected function __construct(string $scriptSource, string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);
        $this->scriptSource = $scriptSource;
    }

    public static function make(string $scriptSource, string $property, ?string $alias = null): static
    {
        return new static($scriptSource, $property, $alias);
    }

    public function type(string $type): static
    {
        $this->type = $type;
        return $this;
    }

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
     * @param array<string, mixed> $nested
     */
    public function nested(array $nested): static
    {
        $this->nested = $nested;
        return $this;
    }

    public function getType(): string
    {
        return 'script';
    }

    public function handle(SearchBuilder $builder, string $direction): void
    {
        $script = ['source' => $this->scriptSource];

        if (! empty($this->params)) {
            $script['params'] = $this->params;
        }

        $sort = Sort::script($script, $this->type)->order($direction);

        if ($this->mode !== null) {
            $sort->mode($this->mode);
        }

        if ($this->nested !== null) {
            $sort->nested($this->nested);
        }

        $builder->sort($sort);
    }
}
