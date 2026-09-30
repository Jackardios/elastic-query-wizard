<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;

class GeoModelFactory extends Factory
{
    protected $model = GeoModel::class;

    public function definition(): array
    {
        // moscow coordinates
        return [
            'name' => $this->faker->name(),
            'lat' => $this->faker->latitude(55.105673, 56.056992),
            'lon' => $this->faker->longitude(36.461995, 38.309071),
        ];
    }
}
