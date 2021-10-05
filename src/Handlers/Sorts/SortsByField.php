<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Sorts;

class SortsByField extends AbstractElasticSort
{
    public function handle($queryHandler, $queryBuilder, string $direction): void
    {
        $queryBuilder->sort($this->getPropertyName(), $direction);
    }
}
