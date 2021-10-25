<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

class CallbackFilter extends AbstractElasticFilter
{
    /**
     * @var callable(\Jackardios\ElasticQueryWizard\Handlers\ElasticQueryHandler, \ElasticScoutDriverPlus\Builders\SearchRequestBuilder, mixed): mixed
     */
    private $callback;

    /**
     * @param string $propertyName
     * @param callable(\Jackardios\ElasticQueryWizard\Handlers\ElasticQueryHandler, \ElasticScoutDriverPlus\Builders\SearchRequestBuilder, mixed): mixed $callback
     * @param string|null $alias
     * @param mixed $default
     */
    public function __construct(string $propertyName, callable $callback, ?string $alias = null, $default = null)
    {
        parent::__construct($propertyName, $alias, $default);

        $this->callback = $callback;
    }

    public function handle($queryHandler, $queryBuilder, $value): void
    {
        call_user_func($this->callback, $queryHandler, $queryBuilder, $value);
    }
}
