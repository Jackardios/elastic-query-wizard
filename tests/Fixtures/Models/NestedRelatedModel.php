<?php

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NestedRelatedModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class);
    }
}
