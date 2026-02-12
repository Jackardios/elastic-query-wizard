<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

final class GeoBoundingBoxFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'geo_bounding_box';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        if (empty($value)) {
            return;
        }

        $propertyName = $this->property;

        [$left, $bottom, $right, $top] = FilterValueSanitizer::geoBoundingBoxValue($value, $propertyName);

        $query = Query::geoBoundingBox($propertyName, $top, $left, $bottom, $right);
        $query = $this->applyParametersOnQuery($query);

        $builder->filter($query);
    }
}
