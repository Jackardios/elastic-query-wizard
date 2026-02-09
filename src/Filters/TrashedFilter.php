<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\EsScoutDriver\Search\SearchBuilder;

class TrashedFilter extends AbstractElasticFilter
{
    protected function __construct(?string $alias = null)
    {
        parent::__construct('trashed', $alias);
    }

    public static function make(?string $alias = null): static
    {
        return new static($alias);
    }

    public function getType(): string
    {
        return 'trashed';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        if ($value === 'with') {
            $builder->boolQuery()->withTrashed();

            return;
        }

        if ($value === 'only') {
            $builder->boolQuery()->onlyTrashed();

            return;
        }
    }
}
