<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;

class GeoDistanceFilter extends ElasticFilter
{
    public function handle($queryWizard, $queryBuilder, $value): void
    {
        if (empty($value)) {
            return;
        }

        $propertyName = $this->getPropertyName();

        ['lon' => $lon, 'lat' => $lat, 'distance' => $distance] = FilterValueSanitizer::geoDistanceValue($value, $propertyName);

        $queryWizard->getRootBoolQuery()->filter([
            'geo_distance' => [
                "distance" => $distance,
                $propertyName => [
                    'lon' => $lon,
                    'lat' => $lat
                ]
            ]
        ]);
    }
}
