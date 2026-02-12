<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\EsScoutDriver\Search\SearchBuilder;

final class TrashedFilter extends AbstractElasticFilter
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
        if ($value === true) {
            $value = 'with';
        } elseif ($value === false) {
            $value = 'without';
        }

        $normalized = is_string($value) ? strtolower($value) : $value;

        if ($normalized === 'true') {
            $normalized = 'with';
        } elseif ($normalized === 'false') {
            $normalized = 'without';
        }

        if ($normalized === 'with') {
            $builder->boolQuery()->withTrashed();

            return;
        }

        if ($normalized === 'only') {
            $builder->boolQuery()->onlyTrashed();

            return;
        }

        if ($normalized === 'without') {
            $builder->boolQuery()->excludeTrashed();
        }
    }
}
