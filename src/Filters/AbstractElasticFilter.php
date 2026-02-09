<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Filters\AbstractFilter;

abstract class AbstractElasticFilter extends AbstractFilter
{
    abstract public function handle(SearchBuilder $builder, mixed $value): void;

    public function apply(mixed $subject, mixed $value): mixed
    {
        return $subject;
    }
}
