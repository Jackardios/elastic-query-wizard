<?php

use Faker\Generator as Faker;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Illuminate\Database\Eloquent\Factory;

/** @var Factory $factory */
$factory->define(TestModel::class, function (Faker $faker) {
    return [
        'name' => $faker->name,
        'category' => $faker->word,
    ];
});
