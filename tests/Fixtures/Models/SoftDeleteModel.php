<?php

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Elastic\ScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftDeleteModel extends Model
{
    use Searchable;
    use SoftDeletes;

    protected $guarded = [];

    public $timestamps = false;
}
