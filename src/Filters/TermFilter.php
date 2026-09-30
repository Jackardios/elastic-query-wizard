<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Concerns\ReadsNumbers;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Query\Term\TermsQuery;
use Jackardios\EsScoutDriver\Support\Query;

final class TermFilter extends AbstractElasticFilter
{
    use HasParameters;
    use ReadsNumbers;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
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

        $prepared = $this->readNumbers($prepared);

        $query = count($prepared) === 1
            ? Query::term($this->property, $prepared[0])
            : Query::terms($this->property, $prepared);

        return $this->applyParametersOnQuery($query);
    }
}
