<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Includes;

class CountInclude extends AbstractElasticInclude
{
    public function __construct(string $include, ?string $alias = null)
    {
        if (empty($alias)) {
            $alias = $include.config('query-wizard.count_suffix');
        }
        parent::__construct($include, $alias);
    }

    /** {@inheritdoc} */
    public function handle($queryHandler, $queryBuilder): void
    {
        $queryBuilder->withCount($this->getInclude());
    }
}
