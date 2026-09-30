<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

final class MatchFilter extends AbstractTextFilter
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [MatchQuery::class];
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::Must;
    }

    protected function buildTextQuery(string $text): QueryInterface
    {
        return Query::match($this->property, $text);
    }
}
