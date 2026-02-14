<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Groups;

use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;

/**
 * Groups child filters into a bool query.
 *
 * Useful for creating OR conditions with minimum_should_match,
 * or logical groupings of filters.
 *
 * @example
 * ElasticGroup::bool('advanced')
 *     ->minimumShouldMatch(1)
 *     ->inFilter()
 *     ->children([
 *         ElasticFilter::term(field: 'status', key: 'status')->inShould(),
 *         ElasticFilter::term(field: 'priority', key: 'priority')->inShould(),
 *     ])
 */
final class BoolGroup extends AbstractElasticGroup
{
    protected int|string|null $minimumShouldMatch = null;

    protected ?float $boost = null;

    protected function __construct(string $scope, ?string $alias = null)
    {
        parent::__construct($scope, $alias);
    }

    public static function make(string $scope, ?string $alias = null): static
    {
        return new static($scope, $alias);
    }

    /**
     * Set minimum_should_match for the bool query.
     *
     * @param int|string $value Number or percentage (e.g., 1, '30%', '2<75%')
     */
    public function minimumShouldMatch(int|string $value): static
    {
        $this->minimumShouldMatch = $value;

        return $this;
    }

    public function getMinimumShouldMatch(): int|string|null
    {
        return $this->minimumShouldMatch;
    }

    /**
     * Set boost for the bool query to influence relevance scoring.
     */
    public function boost(float $value): static
    {
        $this->boost = $value;

        return $this;
    }

    public function getBoost(): ?float
    {
        return $this->boost;
    }

    public function getType(): string
    {
        return 'bool_group';
    }

    public function buildGroupQuery(array $childValues): ?QueryInterface
    {
        if (empty($childValues) || empty($this->children)) {
            return null;
        }

        $innerBoolQuery = new BoolQuery();

        $this->applyChildrenToQuery($innerBoolQuery, $childValues);

        if ($innerBoolQuery->isEmpty()) {
            return null;
        }

        if ($this->minimumShouldMatch !== null) {
            $innerBoolQuery->minimumShouldMatch($this->minimumShouldMatch);
        }

        if ($this->boost !== null) {
            $innerBoolQuery->boost($this->boost);
        }

        return $innerBoolQuery;
    }
}
