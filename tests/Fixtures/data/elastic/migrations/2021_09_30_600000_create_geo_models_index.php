<?php

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

final class CreateGeoModelsIndex implements MigrationInterface
{
    public function up(): void
    {
        Index::create('geo_models', static function (Mapping $mapping, Settings $settings) {
            $mapping->integer('id');
            $mapping->text('name');
            $mapping->geoPoint('location');
        });
    }

    public function down(): void
    {
        Index::dropIfExists('geo_models');
    }
}
