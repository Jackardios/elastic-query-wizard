<?php

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

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
