<?php

namespace Jackardios\ElasticQueryWizard\Tests\App\Models;

use ElasticScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeleteModel extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $guarded = [];

    public $timestamps = false;
}
