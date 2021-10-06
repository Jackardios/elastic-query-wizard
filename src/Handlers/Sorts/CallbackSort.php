<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Sorts;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\ElasticQueryWizard\Handlers\ElasticQueryHandler;

class CallbackSort extends AbstractElasticSort
{
    /**
     * @var callable(ElasticQueryHandler, Builder, string, string):mixed
     */
    private $callback;

    /**
     * @param string $propertyName
     * @param callable(ElasticQueryHandler, Builder, string, string):mixed $callback
     * @param string|null $alias
     */
    public function __construct(string $propertyName, callable $callback, ?string $alias = null)
    {
        parent::__construct($propertyName, $alias);

        $this->callback = $callback;
    }

    /** {@inheritdoc} */
    public function handle($queryHandler, $queryBuilder, string $direction): void
    {
        call_user_func($this->callback, $queryHandler, $queryBuilder, $direction, $this->getPropertyName());
    }
}
