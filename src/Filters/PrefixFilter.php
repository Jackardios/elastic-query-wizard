<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

final class PrefixFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'prefix';
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        if (is_array($value)) {
            $extracted = reset($value);
            $value = $extracted !== false ? $extracted : '';
        }

        if (FilterValueSanitizer::isBlank($value)) {
            return null;
        }

        $query = Query::prefix($this->property, (string) $value);

        return $this->applyParametersOnQuery($query);
    }
}
