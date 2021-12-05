<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;

class GeoDistanceFilter extends AbstractElasticFilter
{
    public function handle($queryHandler, $queryBuilder, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (! (is_array($value) && isset($value['lat'], $value['lon'], $value['distance']))) {
            throw InvalidGeoDistanceValue::make($this->getName());
        }

        $propertyName = $this->getPropertyName();

        ['lon' => $lon, 'lat' => $lat, 'distance' => $distance] = $value;

        $queryHandler->filter([
            'geo_distance' => [
                "distance" => $distance,
                $propertyName => [
                    'lon' => (float) $lon,
                    'lat' => (float) $lat
                ]
            ]
        ]);
    }
}
