<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

class TrashedFilter extends AbstractElasticFilter
{
    public function __construct(string $propertyName = "trashed", ?string $alias = null, $default = null)
    {
        parent::__construct($propertyName, $alias, $default);
    }

    public function handle($queryHandler, $queryBuilder, $value): void
    {
        if ($value === 'with') {
            $queryHandler->withTrashed();

            return;
        }

        if ($value === 'only') {
            $queryHandler->onlyTrashed();

            return;
        }
    }
}
