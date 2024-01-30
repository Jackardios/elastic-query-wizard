<?php

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

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
