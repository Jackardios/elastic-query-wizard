<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\Concerns\ReadsNumbers;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\Term\IdsQuery;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Exceptions\InvalidFilterValue;

final class IdsFilter extends AbstractElasticFilter
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
        return [IdsQuery::class];
    }

    public function validateValueShape(mixed $value): ?string
    {
        return $this->validateScalarOrFlatListValueShape($value);
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        $prepared = FilterValueSanitizer::toArray($value);

        if ($prepared === []) {
            return null;
        }

        foreach ($prepared as $id) {
            if (is_bool($id)) {
                throw InvalidFilterValue::make($value, $this, 'Expected document ids, not a boolean.');
            }
        }

        $stringIds = [];

        foreach ($this->readNumbers($prepared) as $id) {
            if (is_scalar($id) && (string) $id !== '') {
                $stringIds[] = (string) $id;
            }
        }

        if ($stringIds === []) {
            return null;
        }

        $query = Query::ids($stringIds);

        return $this->applyParametersOnQuery($query);
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
