<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;

class TrashedFilter extends ElasticFilter
{
    public function __construct(string $propertyName = "trashed", ?string $alias = null, $default = null)
    {
        parent::__construct($propertyName, $alias, $default);
    }

    public function handle($queryWizard, $queryBuilder, $value): void
    {
        if ($value === 'with') {
            $queryWizard->withTrashed();

            return;
        }

        if ($value === 'only') {
            $queryWizard->onlyTrashed();

            return;
        }
    }
}
