<?php

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

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
