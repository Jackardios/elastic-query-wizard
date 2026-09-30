<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\Geo\GeoBoundingBoxQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

final class GeoBoundingBoxFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    public function getType(): string
    {
        return 'geo_bounding_box';
    }

    /** @return list<class-string> */
    protected function parameterQueryClasses(): array
    {
        return [GeoBoundingBoxQuery::class];
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        if (FilterValueSanitizer::isBlank($value)) {
            return null;
        }

        [$left, $bottom, $right, $top] = FilterValueSanitizer::geoBoundingBoxValue($value, $this);

        $query = Query::geoBoundingBox($this->property, $top, $left, $bottom, $right);

        return $this->applyParametersOnQuery($query);
    }

    protected function supportsBooleanValues(): bool
    {
        return false;
    }
}
