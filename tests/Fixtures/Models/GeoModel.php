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
 * @property array|null $boundary
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
        'boundary' => 'array',
    ];

    public function toSearchableArray(): array
    {
        $searchableArray = $this->toArray();

        $searchableArray['location'] = $this->location->getCoordinates();

        if ($this->boundary !== null) {
            $searchableArray['boundary'] = $this->boundary;
        }

        return $searchableArray;
    }
}
