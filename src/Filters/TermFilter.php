<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Query\Term\TermsQuery;
use Jackardios\EsScoutDriver\Support\Query;

final class TermFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function getType(): string
    {
        return 'term';
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [TermQuery::class, TermsQuery::class];
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrFlatListValueShape($value);
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        $prepared = FilterValueSanitizer::toScalarArray($value);

        if ($prepared === []) {
            return null;
        }

        $query = count($prepared) === 1
            ? Query::term($this->property, $prepared[0])
            : Query::terms($this->property, $prepared);

        return $this->applyParametersOnQuery($query);
    }
}
