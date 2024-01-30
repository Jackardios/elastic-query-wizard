<?php

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Elastic\ScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Model;
use MatanYadaev\EloquentSpatial\Objects\Point;

/**
 * @property Point $location
 */
class GeoModel extends Model
{
    use Searchable;

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
