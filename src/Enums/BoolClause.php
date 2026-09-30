<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Enums;

use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;

enum BoolClause: string
{
    case Filter = 'filter';
    case Must = 'must';
    case Should = 'should';
    case MustNot = 'must_not';

    /**
     * Add the query to this clause of the bool query.
     *
     * @param  QueryInterface|array<string, mixed>  $query
     *
     * @internal
     */
    public function addTo(BoolQuery $boolQuery, QueryInterface|array $query): void
    {
        match ($this) {
            self::Filter => $boolQuery->addFilter($query),
            self::Must => $boolQuery->addMust($query),
            self::Should => $boolQuery->addShould($query),
            self::MustNot => $boolQuery->addMustNot($query),
        };
    }
}
