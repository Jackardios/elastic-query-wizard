<?php

use ElasticAdapter\Indices\Mapping;
use ElasticAdapter\Indices\Settings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class CreateTestModelsIndex implements MigrationInterface
{
    public function up(): void
    {
        Index::create('test_models', static function (Mapping $mapping, Settings $settings) {
            $mapping->integer('id');
            $mapping->text('name');
            $mapping->keyword('category');
            $mapping->boolean('is_visible');
            $mapping->date('created_at');
            $mapping->date('deleted_at');
        });
    }

    public function down(): void
    {
        Index::dropIfExists('test_models');
    }
}
