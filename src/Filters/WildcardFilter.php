<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\WildcardQuery;
use Jackardios\EsScoutDriver\Support\Query;

final class WildcardFilter extends AbstractTextFilter
{
    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [WildcardQuery::class];
    }

    protected function buildTextQuery(string $text): QueryInterface
    {
        return Query::wildcard($this->property, $text);
    }
}
