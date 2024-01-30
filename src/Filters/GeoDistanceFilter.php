<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoDistanceValue;

class GeoDistanceFilter extends ElasticFilter
{
    public function handle($queryWizard, $queryBuilder, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (! (is_array($value) && isset($value['lat'], $value['lon'], $value['distance']))) {
            throw InvalidGeoDistanceValue::make($this->getName());
        }

        $propertyName = $this->getPropertyName();

        ['lon' => $lon, 'lat' => $lat, 'distance' => $distance] = $value;

        $queryWizard->getRootBoolQuery()->filter([
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
