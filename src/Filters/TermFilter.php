<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

final class TermFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'term';
    }

    public function buildQuery(mixed $value): QueryInterface|array|null
    {
        $prepared = FilterValueSanitizer::toArray($value);

        if (empty($prepared)) {
            return null;
        }

        $query = count($prepared) === 1
            ? Query::term($this->property, $prepared[0])
            : Query::terms($this->property, $prepared);

        return $this->applyParametersOnQuery($query);
    }
}
