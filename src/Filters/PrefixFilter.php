<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\PrefixQuery;
use Jackardios\EsScoutDriver\Support\Query;

final class PrefixFilter extends AbstractTextFilter
{
    /**
     * Elasticsearch's default `index.max_regex_length`, which also bounds a
     * prefix: a longer one fails the search, so it is refused with a 400
     * first. maxLength() changes it for an index with another limit.
     */
    private const ES_MAX_PREFIX_LENGTH = 1000;

    protected function __construct(string $property, ?string $alias = null)
    {
        parent::__construct($property, $alias);

        $this->maxLength = self::ES_MAX_PREFIX_LENGTH;
    }

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [PrefixQuery::class];
    }

    protected function buildTextQuery(string $text): QueryInterface
    {
        return Query::prefix($this->property, $text);
    }
}
