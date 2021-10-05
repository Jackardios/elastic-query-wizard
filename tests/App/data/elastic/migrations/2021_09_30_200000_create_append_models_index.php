<?php

use ElasticAdapter\Indices\Mapping;
use ElasticAdapter\Indices\Settings;
use ElasticMigrations\Facades\Index;
use ElasticMigrations\MigrationInterface;

final class CreateAppendModelsIndex implements MigrationInterface
{
    public function up(): void
    {
        Index::create('append_models', static function (Mapping $mapping, Settings $settings) {
            $mapping->integer('id');
            $mapping->text('firstname');
            $mapping->text('lastname');
        });
    }

    public function down(): void
    {
        Index::dropIfExists('append_models');
    }
}
