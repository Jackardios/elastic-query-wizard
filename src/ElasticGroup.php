<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Jackardios\ElasticQueryWizard\Groups\BoolGroup;
use Jackardios\ElasticQueryWizard\Groups\NestedGroup;

/**
 * Factory for creating filter groups.
 *
 * Groups allow nesting filters in bool or nested query structures,
 * enabling complex query compositions like:
 *
 * - OR conditions with minimum_should_match
 * - Nested document queries with multiple conditions
 * - Logical groupings of filters with boost
 *
 * @example BoolGroup with OR conditions and boost
 * ElasticGroup::bool('advanced')
 *     ->minimumShouldMatch(1)
 *     ->boost(1.5)
 *     ->inFilter()
 *     ->children([
 *         ElasticFilter::term(field: 'status', key: 'status')->inShould(),
 *         ElasticFilter::term(field: 'priority', key: 'priority')->inShould(),
 *     ])
 *
 * @example NestedGroup with inner_hits
 * ElasticGroup::nested('comments')
 *     ->scoreMode('avg')
 *     ->innerHits(['size' => 3, 'sort' => [['date' => 'desc']]])
 *     ->inFilter()
 *     ->children([
 *         ElasticFilter::term(field: 'comments.status', key: 'status'),
 *         ElasticFilter::match(field: 'comments.text', key: 'search')->inMust(),
 *     ])
 */
final class ElasticGroup
{
    /**
     * Create a nested group for filtering on nested document fields.
     *
     * @param string $path The nested document path (e.g., 'comments', 'variants')
     * @param string|null $alias Optional alias for the group (defaults to path)
     */
    public static function nested(string $path, ?string $alias = null): NestedGroup
    {
        return NestedGroup::make($path, $alias);
    }

    /**
     * Create a bool group for logical groupings of filters.
     *
     * @param string $scope Internal group scope name (NOT used as a URL filter key)
     * @param string|null $alias Optional alias for the group
     */
    public static function bool(string $scope, ?string $alias = null): BoolGroup
    {
        return BoolGroup::make($scope, $alias);
    }
}
