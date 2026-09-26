<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\FuzzyQuery;
use Jackardios\EsScoutDriver\Support\Query;

final class FuzzyFilter extends AbstractElasticFilter
{
    use HasParameters;

    /**
     * The value is one pattern, which may contain the separator; call
     * withValueSplitting() to accept a list.
     */
    protected bool $splitValues = false;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function getType(): string
    {
        return 'fuzzy';
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [FuzzyQuery::class];
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrBlankValueShape($value);
    }

    protected function getDefaultClause(): BoolClause
    {
        return BoolClause::MUST;
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        $prepared = FilterValueSanitizer::toString($value);

        if ($prepared === null || $prepared === '') {
            return null;
        }

        $query = Query::fuzzy($this->property, $prepared);

        return $this->applyParametersOnQuery($query);
    }
}
