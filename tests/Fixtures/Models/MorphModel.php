<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Jackardios\EsScoutDriver\Searchable;

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
