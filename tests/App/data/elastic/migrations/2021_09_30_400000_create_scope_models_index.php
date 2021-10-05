<?php

use ElasticAdapter\Indices\Mapping;
use ElasticAdapter\Indices\Settings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class CreateScopeModelsIndex implements MigrationInterface
{
    public function up(): void
    {
        Index::create('scope_models', static function (Mapping $mapping, Settings $settings) {
            $mapping->integer('id');
            $mapping->text('name');
        });
    }

    public function down(): void
    {
        Index::dropIfExists('scope_models');
    }
}
