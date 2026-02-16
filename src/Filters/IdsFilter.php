<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

final class IdsFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'ids';
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        $prepared = FilterValueSanitizer::toArray($value);

        if ($prepared === []) {
            return null;
        }

        // Filter to strings only (IDs must be strings)
        /** @var array<int, string> $stringIds */
        $stringIds = array_values(array_filter(
            array_map(static fn($v) => is_scalar($v) ? (string) $v : null, $prepared),
            static fn($v) => $v !== null && $v !== ''
        ));

        if ($stringIds === []) {
            return null;
        }

        $query = Query::ids($stringIds);

        return $this->applyParametersOnQuery($query);
    }
}
