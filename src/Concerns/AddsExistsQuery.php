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
                BoolClause::FILTER, BoolClause::MUST => $boolQuery->addMustNot($exists),
                BoolClause::SHOULD => $boolQuery->addShould(Query::bool()->addMustNot($exists)),
                BoolClause::MUST_NOT => $boolQuery->addFilter($exists),
            };

            return;
        }

        match ($clause) {
            BoolClause::FILTER => $boolQuery->addFilter($exists),
            BoolClause::MUST => $boolQuery->addMust($exists),
            BoolClause::SHOULD => $boolQuery->addShould($exists),
            BoolClause::MUST_NOT => $boolQuery->addMustNot($exists),
        };
    }
}
