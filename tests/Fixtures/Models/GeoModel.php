<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories\GeoModelFactory;
use Jackardios\EsScoutDriver\Searchable;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * @property Point $location
 */
class GeoModel extends Model
{
    use HasFactory;
    use Searchable;

    protected static function newFactory(): GeoModelFactory
    {
        return GeoModelFactory::new();
    }

    protected $guarded = [];

    protected $casts = [
        'location' => Point::class,
    ];

    public function toSearchableArray()
    {
        $searchableArray = $this->toArray();

        $searchableArray['location'] = $this->location->getCoordinates();

        return $searchableArray;
    }
}
