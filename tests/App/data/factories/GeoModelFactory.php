<?php

use Faker\Generator as Faker;
use Illuminate\Database\Eloquent\Factory;
use Jackardios\ElasticQueryWizard\Tests\App\Models\GeoModel;
use MatanYadaev\EloquentSpatial\Objects\Point;

/** @var Factory $factory */
$factory->define(GeoModel::class, function (Faker $faker) {
    // moscow coordinates
    $lon = $faker->longitude(36.461995, 38.309071);
    $lat = $faker->latitude(55.105673, 56.056992);

    return [
        'name' => $faker->name,
        'location' => new Point($lat, $lon)
    ];
});
