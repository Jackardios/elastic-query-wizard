<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\NestedModel;

class NestedModelFactory extends Factory
{
    protected $model = NestedModel::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(3),
            'variants' => [
                [
                    'sku' => $this->faker->unique()->bothify('SKU-####'),
                    'price' => $this->faker->randomFloat(2, 10, 1000),
                    'active' => $this->faker->boolean(80),
                ],
            ],
            'comments' => [
                [
                    'author' => $this->faker->firstName,
                    'text' => $this->faker->paragraph,
                    'rating' => $this->faker->numberBetween(1, 5),
                ],
            ],
        ];
    }

    public function withVariants(array $variants): static
    {
        return $this->state(fn() => ['variants' => $variants]);
    }

    public function withComments(array $comments): static
    {
        return $this->state(fn() => ['comments' => $comments]);
    }
}
