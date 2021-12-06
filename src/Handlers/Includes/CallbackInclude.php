<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Includes;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;

class CallbackInclude extends AbstractElasticInclude
{
    /**
     * @var callable(AbstractQueryHandler, Builder, string):mixed
     */
    private $callback;

    /**
     * @param string $include
     * @param callable(AbstractQueryHandler, Builder, string):mixed $callback
     * @param string|null $alias
     */
    public function __construct(string $include, callable $callback, ?string $alias = null)
    {
        parent::__construct($include, $alias);

        $this->callback = $callback;
    }

    /** {@inheritdoc} */
    public function handle($queryHandler, $queryBuilder): void
    {
        call_user_func($this->callback, $queryHandler, $queryBuilder, $this->getInclude());
    }
}
