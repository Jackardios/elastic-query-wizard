<?php

namespace Jackardios\ElasticQueryWizard\Sorts;

use Elastic\ScoutDriverPlus\Builders\SearchParametersBuilder;
use Jackardios\ElasticQueryWizard\ElasticSort;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;

class CallbackSort extends ElasticSort
{
    /**
     * @var callable(ElasticQueryWizard, SearchParametersBuilder, string, string):mixed
     */
    private $callback;

    /**
     * @param string $propertyName
     * @param callable(ElasticQueryWizard, SearchParametersBuilder, string, string):mixed $callback
     * @param string|null $alias
     */
    public function __construct(string $propertyName, callable $callback, ?string $alias = null)
    {
        parent::__construct($propertyName, $alias);

        $this->callback = $callback;
    }

    /** {@inheritdoc} */
    public function handle($queryWizard, $queryBuilder, string $direction): void
    {
        call_user_func($this->callback, $queryWizard, $queryBuilder, $direction, $this->getPropertyName());
    }
}
