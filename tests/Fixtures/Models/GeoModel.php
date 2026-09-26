<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Factories\GeoModelFactory;
use Jackardios\EsScoutDriver\Searchable;

/**
 * @property float $lat
 * @property float $lon
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
        'lat' => 'float',
        'lon' => 'float',
        'boundary' => 'array',
    ];

    public function toSearchableArray(): array
    {
        $searchableArray = $this->toArray();
        unset($searchableArray['lat'], $searchableArray['lon']);

        $searchableArray['location'] = ['lat' => $this->lat, 'lon' => $this->lon];

        if ($this->boundary !== null) {
            $searchableArray['boundary'] = $this->boundary;
        }

        return $searchableArray;
    }
}
