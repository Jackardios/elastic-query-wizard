<?php

namespace Jackardios\ElasticQueryWizard\Tests\App\Models;

use ElasticScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Model;

class AppendModel extends Model
{
    use Searchable;

    protected $guarded = [];

    public $timestamps = false;

    public function getFullnameAttribute(): string
    {
        return $this->firstname.' '.$this->lastname;
    }

    public function getReversenameAttribute(): string
    {
        return $this->lastname.' '.$this->firstname;
    }
}
