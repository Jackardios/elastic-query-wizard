<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\EsScoutDriver\Query\FullText\SimpleQueryStringQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Elasticsearch simple query-string syntax, which has no field-qualified terms.
 *
 * Searches the property unless withParameters() sets `fields`.
 */
final class SimpleQueryStringFilter extends AbstractTextFilter
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [SimpleQueryStringQuery::class];
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::Must;
    }

    protected function buildTextQuery(string $text): QueryInterface
    {
        $query = Query::simpleQueryString($text);

        if (! $this->hasQueryParameter('fields')) {
            $query->fields([$this->property]);
        }

        return $query;
    }
}
