<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Groups;

use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\QueryWizard\Contracts\FilterInterface;

/**
 * Interface for filter groups that contain child filters.
 *
 * Groups allow nesting filters in bool or nested query structures.
 * Child filters are applied to an inner BoolQuery, which is then
 * wrapped and added to the parent query context.
 */
interface GroupInterface extends FilterInterface
{
    /**
     * Build the group query from child filter values.
     *
     * @param array<string, mixed> $childValues Map of child filter names to their values
     */
    public function buildGroupQuery(array $childValues): ?QueryInterface;
    /**
     * Set the child filters for this group.
     *
     * @param array<FilterInterface> $children
     */
    public function children(array $children): static;

    /**
     * Get the child filters.
     *
     * @return array<FilterInterface>
     */
    public function getChildren(): array;

    /**
     * Get the names of all child filters (for validation).
     *
     * @return array<int, string>
     */
    public function getChildFilterNames(): array;
}
