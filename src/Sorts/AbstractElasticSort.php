<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Sorts\AbstractSort;

abstract class AbstractElasticSort extends AbstractSort
{
    /**
     * @param 'asc'|'desc' $direction
     */
    abstract public function handle(SearchBuilder $builder, string $direction): void;

    public function apply(mixed $subject, string $direction): mixed
    {
        if ($subject instanceof SearchBuilder) {
            $this->handle($subject, $direction);
        }

        return $subject;
    }
}
