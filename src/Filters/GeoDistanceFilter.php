<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

final class GeoDistanceFilter extends AbstractElasticFilter
{
    use HasParameters;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    public function getType(): string
    {
        return 'geo_distance';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        if (empty($value)) {
            return;
        }

        $propertyName = $this->property;

        ['lon' => $lon, 'lat' => $lat, 'distance' => $distance] = FilterValueSanitizer::geoDistanceValue($value, $propertyName);

        $query = Query::geoDistance($propertyName, $lat, $lon, $distance);
        $query = $this->applyParametersOnQuery($query);

        $builder->filter($query);
    }
}
