<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\AddsExistsQuery;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Support\FilterValueParser;

/**
 * A filter whose boolean value asks for documents with or without the field.
 *
 * @internal
 */
abstract class AbstractExistsFilter extends AbstractElasticFilter
{
    use AddsExistsQuery;

    /**
     * Whether the value asks for documents without the field.
     */
    abstract protected function matchesMissingField(bool $value): bool;

    protected function existsQuery(): QueryInterface
    {
        return Query::exists($this->property);
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrBlankValueShape($value);
    }

    /**
     * The value decides between the clause and its negation, so handle() and
     * handleInGroup() add the condition instead.
     */
    public function buildQuery(mixed $value): ?QueryInterface
    {
        return null;
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        $this->addCondition($builder->boolQuery(), $value);
    }

    public function handleInGroup(BoolQuery $innerBoolQuery, mixed $value): void
    {
        $this->addCondition($innerBoolQuery, $value);
    }

    private function addCondition(BoolQuery $boolQuery, mixed $value): void
    {
        $boolean = FilterValueParser::boolean($value, $this);

        if ($boolean === null) {
            return;
        }

        $this->addExistsQuery($boolQuery, $this->existsQuery(), $this->matchesMissingField($boolean));
    }
}
