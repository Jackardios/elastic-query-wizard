<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Groups;

use Closure;
use Jackardios\ElasticQueryWizard\Concerns\HasBoolClause;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\Exceptions\DuplicateGroupChildFilterNameException;
use Jackardios\ElasticQueryWizard\Exceptions\UnsupportedFilterInGroupException;
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\ElasticQueryWizard\Filters\TrashedFilter;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Filters\AbstractFilter;
use LogicException;

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

    /**
     * @throws UnsupportedFilterInGroupException When a child is not an Elasticsearch filter or group, or is a trashed filter
     * @throws DuplicateGroupChildFilterNameException When two leaves of the tree share a name
     */
    public function children(array $children): static
    {
        foreach ($children as $child) {
            if (! $child instanceof GroupInterface && (! $child instanceof AbstractElasticFilter || $child instanceof TrashedFilter)) {
                throw UnsupportedFilterInGroupException::forFilter($child, $this->getName());
            }
        }

        $this->assertUniqueLeafFilterNames($children);
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

    /**
     * @throws LogicException A group has no value of its own; set a default on a child filter
     */
    public function default(mixed $value): static
    {
        throw $this->valueModifierException('default');
    }

    /**
     * @throws LogicException A group has no value of its own; prepare the value of a child filter
     */
    public function prepareValueWith(Closure $callback): static
    {
        throw $this->valueModifierException('prepareValueWith');
    }

    /**
     * @throws LogicException A group has no value of its own; add the condition to a child filter
     */
    public function when(Closure $callback): static
    {
        throw $this->valueModifierException('when');
    }

    /**
     * @throws LogicException A group has no value of its own; read a child filter's value as a boolean
     */
    public function asBoolean(): static
    {
        throw $this->valueModifierException('asBoolean');
    }

    /**
     * @throws LogicException A group has no value of its own; let a child filter read structured input
     */
    public function allowStructuredInput(): static
    {
        throw $this->valueModifierException('allowStructuredInput');
    }

    /**
     * @throws LogicException A group has no value of its own; split a child filter's value
     */
    public function withValueSplitting(): static
    {
        throw $this->valueModifierException('withValueSplitting');
    }

    /**
     * @throws LogicException A group has no value of its own; keep a child filter's value whole
     */
    public function withoutValueSplitting(): static
    {
        throw $this->valueModifierException('withoutValueSplitting');
    }

    private function valueModifierException(string $method): LogicException
    {
        return new LogicException(sprintf(
            'Filter group `%s` has no value of its own, so %s() does not apply to it; call it on a child filter.',
            $this->getName(),
            $method
        ));
    }

    /**
     * Build the group query from child filter values.
     *
     * @param  array<string, mixed>  $childValues  Map of child filter names to their values
     */
    abstract public function buildGroupQuery(array $childValues): ?QueryInterface;

    /**
     * Apply child filters to an inner BoolQuery.
     *
     * @param  array<string, mixed>  $childValues  Map of child filter names to their values
     *
     * @throws UnsupportedFilterInGroupException When an unsupported filter is used in group context
     */
    protected function applyChildrenToQuery(BoolQuery $innerBoolQuery, array $childValues): void
    {
        foreach ($this->children as $child) {
            if ($child instanceof GroupInterface) {
                // Nested group - collect its child values and apply recursively
                $groupChildValues = $this->collectGroupChildValues($child, $childValues);

                if (empty($groupChildValues)) {
                    continue;
                }

                $groupQuery = $child->buildGroupQuery($groupChildValues);

                if ($groupQuery !== null) {
                    $this->addQueryToBoolQuery($innerBoolQuery, $child, $groupQuery);
                }
            } elseif ($child instanceof AbstractElasticFilter) {
                $childName = $child->getName();

                if (! array_key_exists($childName, $childValues)) {
                    continue;
                }

                $child->handleInGroup($innerBoolQuery, $childValues[$childName]);
            } else {
                // Non-elastic filters (CallbackFilter, PassthroughFilter) cannot be used in groups
                throw UnsupportedFilterInGroupException::forFilter($child, $this->getName());
            }
        }
    }

    /**
     * Collect values for a nested group's children from the parent value map.
     *
     * @param  array<string, mixed>  $parentValues
     * @return array<string, mixed>
     */
    protected function collectGroupChildValues(GroupInterface $group, array $parentValues): array
    {
        $groupChildValues = [];

        foreach ($group->getChildFilterNames() as $childName) {
            if (array_key_exists($childName, $parentValues)) {
                $groupChildValues[$childName] = $parentValues[$childName];
            }
        }

        return $groupChildValues;
    }

    /**
     * Add a query to the inner BoolQuery using the filter's effective clause.
     */
    protected function addQueryToBoolQuery(BoolQuery $boolQuery, FilterInterface $filter, QueryInterface $query): void
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
     */
    protected function addQueryToBuilder(BoolQuery $parentBoolQuery, QueryInterface $query): void
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
     * @param  mixed  $subject  SearchBuilder instance
     * @param  mixed  $value  Array of child filter values keyed by filter name
     */
    public function apply(mixed $subject, mixed $value): mixed
    {
        if (! $subject instanceof SearchBuilder || ! is_array($value)) {
            return $subject;
        }

        /** @var array<string, mixed> $childValues */
        $childValues = $value;

        $groupQuery = $this->buildGroupQuery($childValues);

        if ($groupQuery !== null) {
            $this->addQueryToBuilder($subject->boolQuery(), $groupQuery);
        }

        return $subject;
    }

    /**
     * Ensure leaf filter names are unique in this group's children tree.
     *
     * Group names are intentionally excluded. Group values are resolved by leaf
     * filter aliases only, so duplicate leaf names are ambiguous and forbidden.
     *
     * @param  array<FilterInterface>  $children
     */
    protected function assertUniqueLeafFilterNames(array $children): void
    {
        $leafNames = [];

        foreach ($children as $child) {
            if ($child instanceof GroupInterface) {
                $leafNames = array_merge($leafNames, $child->getChildFilterNames());

                continue;
            }

            $leafNames[] = $child->getName();
        }

        $nameCounts = array_count_values($leafNames);
        $duplicates = array_keys(array_filter($nameCounts, static fn (int $count): bool => $count > 1));

        if ($duplicates !== []) {
            throw DuplicateGroupChildFilterNameException::forGroup($this->getName(), $duplicates);
        }
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
