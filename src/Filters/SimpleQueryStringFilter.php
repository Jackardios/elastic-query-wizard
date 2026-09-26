<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\FullText\SimpleQueryStringQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Elasticsearch simple query-string syntax, which has no field-qualified terms.
 *
 * Searches the property unless withParameters() sets `fields`.
 */
final class SimpleQueryStringFilter extends AbstractElasticFilter
{
    use HasParameters;

    /**
     * The value is one text, which may contain the separator; call
     * withValueSplitting() to accept a list.
     */
    protected bool $splitValues = false;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function getType(): string
    {
        return 'simple_query_string';
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [SimpleQueryStringQuery::class];
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrFlatListValueShape($value);
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::MUST;
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        if (is_array($value)) {
            $value = FilterValueSanitizer::arrayToCommaSeparatedString($value);
        }

        $prepared = FilterValueSanitizer::toString($value);

        if ($prepared === null || $prepared === '') {
            return null;
        }

        $query = Query::simpleQueryString($prepared);

        if (! $this->hasQueryParameter('fields')) {
            $query->fields([$this->property]);
        }

        return $this->applyParametersOnQuery($query);
    }
}
