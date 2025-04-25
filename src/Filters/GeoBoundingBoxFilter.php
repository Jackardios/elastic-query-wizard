<?php

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;

class GeoBoundingBoxFilter extends ElasticFilter
{
    public function handle($queryWizard, $queryBuilder, $value): void
    {
        if (empty($value)) {
            return;
        }

        $propertyName = $this->getPropertyName();

        [$left, $bottom, $right, $top] = FilterValueSanitizer::geoBoundingBoxValue($value, $propertyName);

        $queryWizard->getRootBoolQuery()->filter([
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
