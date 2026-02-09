<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;

class TestModelFactory extends Factory
{
    protected $model = TestModel::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name,
            'category' => $this->faker->word,
        ];
    }
}
