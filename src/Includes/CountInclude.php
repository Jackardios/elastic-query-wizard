<?php

namespace Jackardios\ElasticQueryWizard\Includes;

use Jackardios\ElasticQueryWizard\ElasticInclude;

class CountInclude extends ElasticInclude
{
    public function __construct(string $include, ?string $alias = null)
    {
        if (empty($alias)) {
            $alias = $include.config('query-wizard.count_suffix');
        }
        parent::__construct($include, $alias);
    }

    /** {@inheritdoc} */
    public function handle($queryWizard, $queryBuilder): void
    {
        $queryBuilder->withCount($this->getInclude());
    }
}
