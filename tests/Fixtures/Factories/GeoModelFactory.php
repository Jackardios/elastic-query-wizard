<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\EloquentSpatial\Objects\Point;

class GeoModelFactory extends Factory
{
    protected $model = GeoModel::class;

    public function definition(): array
    {
        // moscow coordinates
        $lon = $this->faker->longitude(36.461995, 38.309071);
        $lat = $this->faker->latitude(55.105673, 56.056992);

        return [
            'name' => $this->faker->name,
            'location' => new Point($lon, $lat),
        ];
    }
}
