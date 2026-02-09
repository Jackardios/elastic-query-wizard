<?php

declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

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
