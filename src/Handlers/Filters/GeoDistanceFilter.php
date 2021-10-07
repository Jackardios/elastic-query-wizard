<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;

class GeoDistanceFilter extends AbstractElasticFilter
{
    public function handle($queryHandler, $queryBuilder, $value): void
    {
        if (! (is_array($value) && isset($value['lat'], $value['lat'], $value['distance']))) {
            throw InvalidGeoDistanceValue::make($this->getName());
        }

        $propertyName = $this->getPropertyName();

        ['lat' => $lat, 'lon' => $lon, 'distance' => $distance] = $value;

        $queryHandler->getFiltersBoolQuery()->must([
            'geo_distance' => [
                "distance" => $distance,
                $propertyName => [
                    'lat' => (float) $lat,
                    'lon' => (float) $lon
                ]
            ]
        ]);
    }
}
