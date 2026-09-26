<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RelatedModelWithDefaults extends Model
{
    protected $table = 'related_models';

    protected $guarded = [];

    protected $with = ['nestedRelatedModels'];

    protected $withCount = ['nestedRelatedModels'];

    public $timestamps = false;

    public function nestedRelatedModels(): HasMany
    {
        return $this->hasMany(NestedRelatedModel::class, 'related_model_id');
    }
}
