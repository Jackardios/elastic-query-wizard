<?php

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use ElasticScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class MorphModel extends Model
{
    use Searchable;

    protected $guarded = [];

    public $timestamps = false;

    public function parent(): MorphTo
    {
        return $this->morphTo();
    }
}
