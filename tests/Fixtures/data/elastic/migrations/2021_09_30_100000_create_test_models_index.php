<?php

declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

final class CreateTestModelsIndex implements MigrationInterface
{
    public function up(): void
    {
        Index::create('test_models', static function (Mapping $mapping, Settings $settings) {
            $mapping->integer('id');
            $mapping->text('name', ['fields' => ['keyword' => ['type' => 'keyword']]]);
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
