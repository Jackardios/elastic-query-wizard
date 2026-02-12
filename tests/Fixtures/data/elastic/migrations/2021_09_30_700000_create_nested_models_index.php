<?php

declare(strict_types=1);

use Elastic\Adapter\Indices\Mapping;
use Elastic\Adapter\Indices\Settings;
use Elastic\Migrations\Facades\Index;
use Elastic\Migrations\MigrationInterface;

final class CreateNestedModelsIndex implements MigrationInterface
{
    public function up(): void
    {
        Index::create('nested_models', static function (Mapping $mapping, Settings $settings) {
            $mapping->integer('id');
            $mapping->text('title', ['fields' => ['keyword' => ['type' => 'keyword']]]);
            $mapping->nested('variants', [
                'properties' => [
                    'sku' => ['type' => 'keyword'],
                    'price' => ['type' => 'float'],
                    'active' => ['type' => 'boolean'],
                ],
            ]);
            $mapping->nested('comments', [
                'properties' => [
                    'author' => ['type' => 'keyword'],
                    'text' => ['type' => 'text'],
                    'rating' => ['type' => 'integer'],
                ],
            ]);
        });
    }

    public function down(): void
    {
        Index::dropIfExists('nested_models');
    }
}
