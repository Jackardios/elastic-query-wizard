<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories\AppendModelFactory;
use Jackardios\EsScoutDriver\Searchable;

class AppendModel extends Model
{
    use HasFactory;
    use Searchable;

    protected static function newFactory(): AppendModelFactory
    {
        return AppendModelFactory::new();
    }

    protected $guarded = [];

    public $timestamps = false;

    public function getFullnameAttribute(): string
    {
        return $this->firstname . ' ' . $this->lastname;
    }

    public function getReversenameAttribute(): string
    {
        return $this->lastname . ' ' . $this->firstname;
    }
}
