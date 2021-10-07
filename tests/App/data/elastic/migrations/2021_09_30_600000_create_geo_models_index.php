<?php

use ElasticAdapter\Indices\Mapping;
use ElasticAdapter\Indices\Settings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

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
