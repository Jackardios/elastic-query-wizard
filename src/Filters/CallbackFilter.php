<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;

class CallbackFilter extends ElasticFilter
{
    /**
     * @param callable(ElasticQueryWizard, SearchRequestBuilder, mixed):mixed
     */
    private $callback;

    /**
     * @param string $propertyName
     * @param callable(ElasticQueryWizard, SearchRequestBuilder, mixed):mixed $callback
     * @param string|null $alias
     * @param mixed $default
     */
    public function __construct(string $propertyName, callable $callback, ?string $alias = null, $default = null)
    {
        parent::__construct($propertyName, $alias, $default);

        $this->callback = $callback;
    }

    public function handle($queryWizard, $queryBuilder, $value): void
    {
        call_user_func($this->callback, $queryWizard, $queryBuilder, $value);
    }
}
