<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\EsScoutDriver\Search\SearchBuilder;

/**
 * The callback receives arguments in this order:
 * ($builder, $value, $property)
 */
class CallbackFilter extends AbstractElasticFilter
{
    /** @var callable(SearchBuilder, mixed, string): void */
    private mixed $callback;

    protected function __construct(string $property, callable $callback, ?string $alias = null)
    {
        parent::__construct($property, $alias);

        $this->callback = $callback;
    }

    /**
     * @param callable(SearchBuilder, mixed, string): void $callback
     */
    public static function make(string $property, callable $callback, ?string $alias = null): static
    {
        return new static($property, $callback, $alias);
    }

    public function getType(): string
    {
        return 'callback';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        call_user_func($this->callback, $builder, $value, $this->property);
    }
}
