<?php

use ElasticAdapter\Indices\Mapping;
use ElasticAdapter\Indices\Settings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class CreateMorphModelsIndex implements MigrationInterface
{
    public function up(): void
    {
        Index::create('morph_models', static function (Mapping $mapping, Settings $settings) {
            $mapping->integer('id');
            $mapping->text('name');

        });
    }

    public function down(): void
    {
        Index::dropIfExists('morph_models');
    }
}
