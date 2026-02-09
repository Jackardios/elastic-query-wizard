<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\QueryWizard\Sorts\AbstractSort;

class ScriptSort extends AbstractSort
{
    protected string $scriptSource;
    protected string $type = 'number';
    protected array $params = [];
    protected ?string $mode = null;

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

    public function getType(): string
    {
        return 'script';
    }

    public function apply(mixed $subject, string $direction): mixed
    {
        $script = ['source' => $this->scriptSource];

        if (! empty($this->params)) {
            $script['params'] = $this->params;
        }

        $sort = Sort::script($script, $this->type)->order($direction);

        if ($this->mode !== null) {
            $sort->mode($this->mode);
        }

        $subject->sort($sort);

        return $subject;
    }
}
