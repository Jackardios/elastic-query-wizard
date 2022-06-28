<?php

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\ElasticQueryWizard\ElasticSort;

class FieldSort extends ElasticSort
{
    public function handle($queryWizard, $queryBuilder, string $direction): void
    {
        $queryBuilder->sort($this->getPropertyName(), $direction);
    }
}
