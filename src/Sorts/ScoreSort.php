<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Sort\Sort;

final class ScoreSort extends AbstractElasticSort
{
    public static function make(?string $alias = null): static
    {
        return new self('_score', $alias);
    }

    public function handle(SearchBuilder $builder, string $direction): void
    {
        $builder->sort(Sort::score()->order($direction));
    }
}
