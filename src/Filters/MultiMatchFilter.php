<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use InvalidArgumentException;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\EsScoutDriver\Query\FullText\MultiMatchQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

final class MultiMatchFilter extends AbstractTextFilter
{
    /** @var string[] */
    protected array $fields;

    /**
     * @param  string[]  $fields
     */
    protected function __construct(string $property, array $fields, ?string $alias = null)
    {
        parent::__construct($property, $alias);

        if ($fields === []) {
            throw new InvalidArgumentException(sprintf('Filter `%s` needs at least one field to search.', $this->getName()));
        }

        $this->fields = $fields;
    }

    /**
     * @param  string[]  $fields  The Elasticsearch fields to search across
     *
     * @throws InvalidArgumentException When the list is empty
     */
    public static function make(string $property, array $fields, ?string $alias = null): static
    {
        return new self($property, $fields, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [MultiMatchQuery::class];
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::Must;
    }

    protected function buildTextQuery(string $text): QueryInterface
    {
        return Query::multiMatch($this->fields, $text);
    }
}
