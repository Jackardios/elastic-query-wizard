<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Concerns;

use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Adds a field existence condition, or its negation, to the filter's clause.
 *
 * @internal
 */
trait AddsExistsQuery
{
    abstract public function getEffectiveClause(): BoolClause;

    /**
     * A negation excludes the field through must_not in filter and must, is one
     * of the alternatives in should, and requires the field in must_not.
     */
    protected function addExistsQuery(BoolQuery $boolQuery, QueryInterface $exists, bool $negated): void
    {
        $clause = $this->getEffectiveClause();

        if ($negated) {
            match ($clause) {
                BoolClause::Filter, BoolClause::Must => $boolQuery->addMustNot($exists),
                BoolClause::Should => $boolQuery->addShould(Query::bool()->addMustNot($exists)),
                BoolClause::MustNot => $boolQuery->addFilter($exists),
            };

            return;
        }

        match ($clause) {
            BoolClause::Filter => $boolQuery->addFilter($exists),
            BoolClause::Must => $boolQuery->addMust($exists),
            BoolClause::Should => $boolQuery->addShould($exists),
            BoolClause::MustNot => $boolQuery->addMustNot($exists),
        };
    }
}
