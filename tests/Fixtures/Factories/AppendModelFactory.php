<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\AppendModel;

class AppendModelFactory extends Factory
{
    protected $model = AppendModel::class;

    public function definition(): array
    {
        return [
            'firstname' => $this->faker->firstName,
            'lastname' => $this->faker->lastName,
        ];
    }
}
