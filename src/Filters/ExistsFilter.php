<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

final class ExistsFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function getType(): string
    {
        return 'exists';
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
        if (FilterValueSanitizer::isBlank($value)) {
            return;
        }

        $normalized = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($normalized === null) {
            return;
        }

        $query = Query::exists($this->property);
        $query = $this->applyParametersOnQuery($query);

        // Truthy: field must exist (filter), Falsy: field must NOT exist (must_not)
        $effectiveClause = $normalized
            ? ($this->clause ?? BoolClause::FILTER)
            : BoolClause::MUST_NOT;

        match ($effectiveClause) {
            BoolClause::FILTER => $boolQuery->addFilter($query),
            BoolClause::MUST => $boolQuery->addMust($query),
            BoolClause::SHOULD => $boolQuery->addShould($query),
            BoolClause::MUST_NOT => $boolQuery->addMustNot($query),
        };
    }
}
