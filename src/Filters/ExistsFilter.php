<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\AddsExistsQuery;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\ExistsQuery;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Support\FilterValueParser;

final class ExistsFilter extends AbstractElasticFilter
{
    use AddsExistsQuery;
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [ExistsQuery::class];
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrBlankValueShape($value);
    }

    /**
     * This filter has conditional clause logic (filter vs must_not) so we
     * implement buildQuery to return null, and override handle() for
     * the conditional clause logic.
     */
    public function buildQuery(mixed $value): ?QueryInterface
    {
        // buildQuery returns null because this filter has conditional clause logic
        // that requires handle() to decide between filter and must_not
        return null;
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        $this->applyExistsLogic($builder->boolQuery(), $value);
    }

    public function handleInGroup(BoolQuery $innerBoolQuery, mixed $value): void
    {
        $this->applyExistsLogic($innerBoolQuery, $value);
    }

    protected function applyExistsLogic(BoolQuery $boolQuery, mixed $value): void
    {
        $exists = FilterValueParser::boolean($value, $this);

        if ($exists === null) {
            return;
        }

        $this->addExistsQuery($boolQuery, $this->applyParametersOnQuery(Query::exists($this->property)), ! $exists);
    }
}
