<?php

use ElasticAdapter\Indices\Mapping;
use ElasticAdapter\Indices\Settings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class CreateSoftDeleteModelsIndex implements MigrationInterface
{
    public function up(): void
    {
        Index::create('soft_delete_models', static function (Mapping $mapping, Settings $settings) {
            $mapping->integer('id');
            $mapping->text('name');
        });
    }

    public function down(): void
    {
        Index::dropIfExists('soft_delete_models');
    }
}
