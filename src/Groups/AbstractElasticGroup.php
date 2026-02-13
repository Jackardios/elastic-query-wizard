<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Groups;

use Jackardios\ElasticQueryWizard\Concerns\HasBoolClause;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\Exceptions\UnsupportedFilterInGroupException;
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Filters\AbstractFilter;

/**
 * Base class for filter groups.
 *
 * Groups contain child filters and apply them to an inner BoolQuery,
 * then wrap that query and add it to the parent context.
 */
abstract class AbstractElasticGroup extends AbstractFilter implements GroupInterface
{
    use HasBoolClause;

    /** @var array<FilterInterface> */
    protected array $children = [];

    public function children(array $children): static
    {
        $this->children = $children;

        return $this;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function getChildFilterNames(): array
    {
        $names = [];

        foreach ($this->children as $child) {
            if ($child instanceof GroupInterface) {
                // Recursively collect only leaf filter names, not group names
                $names = array_merge($names, $child->getChildFilterNames());
            } else {
                $names[] = $child->getName();
            }
        }

        return $names;
    }

    public function getType(): string
    {
        return 'group';
    }

    /**
     * Build the group query from child filter values.
     *
     * @param array<string, mixed> $childValues Map of child filter names to their values
     */
    abstract public function buildGroupQuery(array $childValues): ?QueryInterface;

    /**
     * Apply child filters to an inner BoolQuery.
     *
     * @param array<string, mixed> $childValues Map of child filter names to their values
     *
     * @throws UnsupportedFilterInGroupException When a non-elastic filter (e.g., CallbackFilter) is used
     */
    protected function applyChildrenToQuery(BoolQuery $innerBoolQuery, array $childValues): void
    {
        foreach ($this->children as $child) {
            $childName = $child->getName();

            if (! array_key_exists($childName, $childValues)) {
                continue;
            }

            $value = $childValues[$childName];

            if ($child instanceof GroupInterface) {
                // Nested group - collect its child values and apply recursively
                $groupChildValues = $this->collectGroupChildValues($child, $childValues);
                $groupQuery = $child->buildGroupQuery($groupChildValues);

                if ($groupQuery !== null) {
                    $this->addQueryToBoolQuery($innerBoolQuery, $child, $groupQuery);
                }
            } elseif ($child instanceof AbstractElasticFilter) {
                // Use handleInGroup() to properly handle filters with conditional clause logic
                // (ExistsFilter, NullFilter, TrashedFilter)
                $child->handleInGroup($innerBoolQuery, $value);
            } else {
                // Non-elastic filters (CallbackFilter, PassthroughFilter) cannot be used in groups
                throw UnsupportedFilterInGroupException::forFilter($child, $this->getName());
            }
        }
    }

    /**
     * Collect values for a nested group's children from the parent value map.
     *
     * @param array<string, mixed> $parentValues
     * @return array<string, mixed>
     */
    protected function collectGroupChildValues(GroupInterface $group, array $parentValues): array
    {
        $groupChildValues = [];

        foreach ($group->getChildren() as $child) {
            $childName = $child->getName();
            if (array_key_exists($childName, $parentValues)) {
                $groupChildValues[$childName] = $parentValues[$childName];
            }
        }

        return $groupChildValues;
    }

    /**
     * Add a query to the inner BoolQuery using the filter's effective clause.
     *
     * @param QueryInterface|array<string, mixed> $query
     */
    protected function addQueryToBoolQuery(BoolQuery $boolQuery, FilterInterface $filter, QueryInterface|array $query): void
    {
        $clause = BoolClause::FILTER;

        if ($filter instanceof AbstractElasticFilter || $filter instanceof AbstractElasticGroup) {
            $clause = $filter->getEffectiveClause();
        }

        match ($clause) {
            BoolClause::FILTER => $boolQuery->addFilter($query),
            BoolClause::MUST => $boolQuery->addMust($query),
            BoolClause::SHOULD => $boolQuery->addShould($query),
            BoolClause::MUST_NOT => $boolQuery->addMustNot($query),
        };
    }

    /**
     * Add the group query to the parent BoolQuery using this group's clause.
     *
     * @param QueryInterface|array<string, mixed> $query
     */
    protected function addQueryToBuilder(BoolQuery $parentBoolQuery, QueryInterface|array $query): void
    {
        $clause = $this->getEffectiveClause();

        match ($clause) {
            BoolClause::FILTER => $parentBoolQuery->addFilter($query),
            BoolClause::MUST => $parentBoolQuery->addMust($query),
            BoolClause::SHOULD => $parentBoolQuery->addShould($query),
            BoolClause::MUST_NOT => $parentBoolQuery->addMustNot($query),
        };
    }

    /**
     * Apply the group to the subject.
     *
     * The value is expected to be an array of child filter values.
     *
     * @param mixed $subject SearchBuilder instance
     * @param mixed $value Array of child filter values keyed by filter name
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        if (! $subject instanceof SearchBuilder || ! is_array($value)) {
            return $subject;
        }

        $groupQuery = $this->buildGroupQuery($value);

        if ($groupQuery !== null) {
            $this->addQueryToBuilder($subject->boolQuery(), $groupQuery);
        }

        return $subject;
    }
}
