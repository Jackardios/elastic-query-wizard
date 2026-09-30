<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\FuzzyQuery;
use Jackardios\EsScoutDriver\Support\Query;

final class FuzzyFilter extends AbstractTextFilter
{
    /**
     * Elasticsearch builds an automaton per term that costs tens of kilobytes
     * of memory for each character, so a long term can trip a circuit breaker.
     * maxLength() changes or removes the limit.
     */
    private const DEFAULT_MAX_LENGTH = 256;

    protected function __construct(string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);

        $this->maxLength = self::DEFAULT_MAX_LENGTH;
    }

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [FuzzyQuery::class];
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::Must;
    }

    protected function buildTextQuery(string $text): QueryInterface
    {
        return Query::fuzzy($this->property, $text);
    }
}
