<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasBoolClause;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Filters\AbstractFilter;

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
     * @return QueryInterface|array<string, mixed>|null Return null to skip the filter
     */
    abstract public function buildQuery(mixed $value): QueryInterface|array|null;

    /**
     * Whether the value carries nothing to filter on.
     *
     * `?filter[x]=` and `?filter[x][]=` reach a filter as null and [null]: the
     * parameter was sent but left empty, which this package treats as "filter not
     * applied" rather than as an error. Shape rules have to honour that, otherwise
     * a blank multi-value parameter would be rejected by the scalar-only filters
     * while the list-accepting ones silently ignore it.
     *
     * Zero and false are values, not blanks - FilterValueSanitizer::isBlank()
     * already draws that line.
     */
    protected function isBlankValueShape(mixed $value): bool
    {
        if (FilterValueSanitizer::isBlank($value)) {
            return true;
        }

        if (! is_array($value)) {
            return false;
        }

        foreach ($value as $item) {
            if (! FilterValueSanitizer::isBlank($item)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Accept a single scalar, reject a value carrying several.
     *
     * Blank input is passed through as "not applied"; anything else non-scalar
     * would otherwise be silently coerced by taking its first element.
     */
    protected function validateScalarOrBlankValueShape(mixed $value): ?string
    {
        if ($this->isBlankValueShape($value)) {
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
        $clause = $this->getEffectiveClause();

        match ($clause) {
            BoolClause::Filter => $boolQuery->addFilter($query),
            BoolClause::Must => $boolQuery->addMust($query),
            BoolClause::Should => $boolQuery->addShould($query),
            BoolClause::MustNot => $boolQuery->addMustNot($query),
        };
    }
}
