<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Concerns;

use Jackardios\ElasticQueryWizard\Enums\BoolClause;

/**
 * Provides fluent methods for specifying which bool clause a filter/group
 * should be added to (filter, must, should, must_not).
 */
trait HasBoolClause
{
    protected ?BoolClause $clause = null;

    /**
     * Add to the filter clause (non-scoring).
     */
    public function inFilter(): static
    {
        $this->clause = BoolClause::FILTER;

        return $this;
    }

    /**
     * Add to the must clause (scoring).
     */
    public function inMust(): static
    {
        $this->clause = BoolClause::MUST;

        return $this;
    }

    /**
     * Add to the should clause.
     */
    public function inShould(): static
    {
        $this->clause = BoolClause::SHOULD;

        return $this;
    }

    /**
     * Add to the must_not clause.
     */
    public function inMustNot(): static
    {
        $this->clause = BoolClause::MUST_NOT;

        return $this;
    }

    /**
     * Get the explicitly set clause.
     */
    public function getClause(): ?BoolClause
    {
        return $this->clause;
    }

    /**
     * Get the default clause for this filter type.
     * Override in subclasses to change default behavior.
     */
    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::FILTER;
    }

    /**
     * Get the effective clause (explicit or default).
     */
    public function getEffectiveClause(): BoolClause
    {
        return $this->clause ?? $this->getDefaultClause();
    }
}
