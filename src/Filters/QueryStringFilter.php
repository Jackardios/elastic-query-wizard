<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

final class QueryStringFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'query_string';
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::MUST;
    }

    public function buildQuery(mixed $value): QueryInterface|array|null
    {
        if (is_array($value)) {
            $value = FilterValueSanitizer::arrayToCommaSeparatedString($value);
        }

        if (FilterValueSanitizer::isBlank($value)) {
            return null;
        }

        $query = Query::queryString($value);

        return $this->applyParametersOnQuery($query);
    }
}
