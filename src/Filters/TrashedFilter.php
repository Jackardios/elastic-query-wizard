<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Support\FilterValueParser;

final class TrashedFilter extends AbstractElasticFilter
{
    protected function __construct(?string $alias = null)
    {
        parent::__construct('trashed', $alias);
    }

    public static function make(?string $alias = null): static
    {
        return new self($alias);
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrBlankValueShape($value);
    }

    /**
     * This filter doesn't add a query, it modifies the soft delete mode.
     */
    public function buildQuery(mixed $value): ?QueryInterface
    {
        return null;
    }

    /**
     * @throws \LogicException When Scout does not index soft deletes, so no mode could apply
     */
    public function handle(SearchBuilder $builder, mixed $value): void
    {
        $mode = FilterValueParser::trashedMode($value, $this);

        if ($mode !== null && ! config('scout.soft_delete', false)) {
            throw new \LogicException(sprintf(
                'Filter `%s` needs `scout.soft_delete` set to true: without it, Scout does not index which models are trashed.',
                $this->getName()
            ));
        }

        match ($mode) {
            'with' => $builder->withTrashed(),
            'only' => $builder->onlyTrashed(),
            'without' => $builder->excludeTrashed(),
            null => null,
        };
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
