<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoBoundingBoxValue;

class GeoBoundingBoxFilter extends ElasticFilter
{
    public function handle($queryWizard, $queryBuilder, $value): void
    {
        if (empty($value)) {
            return;
        }

        if (! (is_array($value) && count($value) === 4)) {
            throw InvalidGeoBoundingBoxValue::make($this->getName());
        }

        $propertyName = $this->getPropertyName();

        $value = array_map('floatval', $value);

        [$left, $bottom, $right, $top] = $value;

        if ($left === $right) {
            $left += 0.00001;
        }
        if ($top === $bottom) {
            $top -= 0.00001;
        }

        $queryWizard->filter([
            'geo_bounding_box' => [
                $propertyName => [
                    'top_left' => [
                        'lon' => $left,
                        'lat' => $top
                    ],
                    'bottom_right' => [
                        'lon' => $right,
                        'lat' => $bottom
                    ],
                ]
            ]
        ]);
    }
}
