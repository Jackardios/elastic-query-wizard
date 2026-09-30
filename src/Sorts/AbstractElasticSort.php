<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Sorts\AbstractSort;

/**
 * Base class for custom Elasticsearch sorts.
 *
 * @api
 */
abstract class AbstractElasticSort extends AbstractSort
{
    abstract public function handle(SearchBuilder $builder, SortDirection $direction): void;

    public function apply(mixed $subject, SortDirection $direction): mixed
    {
        if ($subject instanceof SearchBuilder) {
            $this->handle($subject, $direction);
        }

        return $subject;
    }
}
