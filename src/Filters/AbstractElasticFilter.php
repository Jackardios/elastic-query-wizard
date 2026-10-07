<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasBoolClause;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Filters\AbstractFilter;
use Jackardios\QueryWizard\Support\FilterValueParser;

/**
 * Base class for custom Elasticsearch filters: buildQuery() returns the query the wizard adds to the filter's bool clause.
 *
 * @api
 */
abstract class AbstractElasticFilter extends AbstractFilter
{
    use HasBoolClause;

    /**
     * Build the Elasticsearch query for the given value.
     *
     * Return QueryInterface for typed DSL queries, or raw array for custom
     * low-level Elasticsearch query fragments.
     *
     * @return QueryInterface|array<string, mixed>|null Return null to skip the filter; an empty array is not a query,
     *                                                  and es-scout-driver throws an `InvalidQueryException` for it
     */
    abstract public function buildQuery(mixed $value): QueryInterface|array|null;

    /**
     * Accept a single scalar, reject a value carrying several.
     *
     * A blank value (see laravel-query-wizard's `FilterValueParser::isBlank()`),
     * such as a list of blanks a value preparer returns, is passed through as
     * "not applied"; anything else non-scalar is refused.
     *
     * @api
     */
    protected function validateScalarOrBlankValueShape(mixed $value): ?string
    {
        if (FilterValueParser::isBlank($value)) {
            return null;
        }

        return $this->validateScalarOnlyValueShape($value);
    }

    /**
     * Handle the filter by building and adding the query to the builder.
     */
    public function handle(SearchBuilder $builder, mixed $value): void
    {
        $query = $this->buildQuery($value);

        if ($query === null) {
            return;
        }

        $this->addQueryToBuilder($builder->boolQuery(), $query);
    }

    /**
     * Handle the filter when used inside a group.
     *
     * This method is called by AbstractElasticGroup::applyChildrenToQuery() instead of
     * buildQuery() to properly handle filters with conditional clause logic (ExistsFilter,
     * NullFilter).
     *
     * Override this method in filters that need special logic when used in groups.
     */
    public function handleInGroup(BoolQuery $innerBoolQuery, mixed $value): void
    {
        $query = $this->buildQuery($value);

        if ($query === null) {
            return;
        }

        $this->addQueryToBuilder($innerBoolQuery, $query);
    }

    public function apply(mixed $subject, mixed $value): mixed
    {
        if ($subject instanceof SearchBuilder) {
            $this->handle($subject, $value);
        }

        return $subject;
    }

    /**
     * Add a query to the BoolQuery using the effective clause.
     *
     * @param  QueryInterface|array<string, mixed>  $query
     */
    protected function addQueryToBuilder(BoolQuery $boolQuery, QueryInterface|array $query): void
    {
        $this->getEffectiveClause()->addTo($boolQuery, $query);
    }
}
