<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories\NestedModelFactory;
use Jackardios\EsScoutDriver\Searchable;

/**
 * @property array $variants
 * @property array $comments
 */
class NestedModel extends Model
{
    use HasFactory;
    use Searchable;

    protected static function newFactory(): NestedModelFactory
    {
        return NestedModelFactory::new();
    }

    protected $guarded = [];

    protected $casts = [
        'variants' => 'array',
        'comments' => 'array',
    ];

    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'variants' => $this->variants ?? [],
            'comments' => $this->comments ?? [],
        ];
    }
}
