<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NestedRelatedModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    /**
     * Get the formatted name attribute.
     */
    public function getFormattedNameAttribute(): string
    {
        return 'Nested: ' . $this->name;
    }

    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class);
    }
}
