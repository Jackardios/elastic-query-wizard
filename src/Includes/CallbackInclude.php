<?php

namespace Jackardios\ElasticQueryWizard\Includes;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\ElasticQueryWizard\ElasticInclude;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;

class CallbackInclude extends ElasticInclude
{
    /**
     * @var callable(ElasticQueryWizard, Builder, string, string):mixed
     */
    private $callback;

    /**
     * @param string $include
     * @var callable(ElasticQueryWizard, Builder, string, string):mixed $callback
     * @param string|null $alias
     */
    public function __construct(string $include, callable $callback, ?string $alias = null)
    {
        parent::__construct($include, $alias);

        $this->callback = $callback;
    }

    /** {@inheritdoc} */
    public function handle($queryWizard, $queryBuilder): void
    {
        call_user_func($this->callback, $queryWizard, $queryBuilder, $this->getInclude());
    }
}
