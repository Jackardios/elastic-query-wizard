<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

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
        return new self($alias);
    }

    public function getType(): string
    {
        return 'trashed';
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

        match ($normalized) {
            'with' => $builder->withTrashed(),
            'only' => $builder->onlyTrashed(),
            'without' => $builder->excludeTrashed(),
            default => null,
        };
    }
}
