<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories\SoftDeleteModelFactory;
use Jackardios\EsScoutDriver\Searchable;

class SoftDeleteModel extends Model
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected static function newFactory(): SoftDeleteModelFactory
    {
        return SoftDeleteModelFactory::new();
    }

    protected $guarded = [];

    public $timestamps = false;
}
