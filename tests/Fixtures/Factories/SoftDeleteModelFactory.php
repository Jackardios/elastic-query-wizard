<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\SoftDeleteModel;

class SoftDeleteModelFactory extends Factory
{
    protected $model = SoftDeleteModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
        ];
    }
}
