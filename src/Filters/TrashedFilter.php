<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
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

    /**
     * This filter doesn't add a query, it modifies the soft delete mode.
     */
    public function buildQuery(mixed $value): QueryInterface|array|null
    {
        return null;
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        $this->applyTrashedLogic($builder->boolQuery(), $value);
    }

    public function handleInGroup(BoolQuery $innerBoolQuery, mixed $value): void
    {
        $this->applyTrashedLogic($innerBoolQuery, $value);
    }

    protected function applyTrashedLogic(BoolQuery $boolQuery, mixed $value): void
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
            $boolQuery->withTrashed();

            return;
        }

        if ($normalized === 'only') {
            $boolQuery->onlyTrashed();

            return;
        }

        if ($normalized === 'without') {
            $boolQuery->excludeTrashed();
        }
    }
}
