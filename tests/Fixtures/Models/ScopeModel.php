<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Jackardios\EsScoutDriver\Searchable;

class ScopeModel extends Model
{
    use Searchable;

    protected $guarded = [];

    public $timestamps = false;

    public static function boot(): void
    {
        parent::boot();

        static::addGlobalScope('nameNotTest', function (Builder $builder) {
            $builder->where('name', '<>', 'test');
        });
    }
}
