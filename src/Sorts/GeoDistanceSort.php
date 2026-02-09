<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Sorts;

use Jackardios\EsScoutDriver\Sort\Sort;
use Jackardios\QueryWizard\Sorts\AbstractSort;

class GeoDistanceSort extends AbstractSort
{
    protected float $lat;
    protected float $lon;
    protected string $unit = 'km';
    protected ?string $mode = null;
    protected ?string $distanceType = null;
    protected ?bool $ignoreUnmapped = null;

    protected function __construct(
        string $property,
        float $lat,
        float $lon,
        ?string $alias = null
    ) {
        parent::__construct($property, $alias);
        $this->lat = $lat;
        $this->lon = $lon;
    }

    public static function make(
        string $property,
        float $lat,
        float $lon,
        ?string $alias = null
    ): static {
        return new static($property, $lat, $lon, $alias);
    }

    public function unit(string $unit): static
    {
        $this->unit = $unit;
        return $this;
    }

    public function mode(string $mode): static
    {
        $this->mode = $mode;
        return $this;
    }

    public function distanceType(string $distanceType): static
    {
        $this->distanceType = $distanceType;
        return $this;
    }

    public function ignoreUnmapped(bool $ignore = true): static
    {
        $this->ignoreUnmapped = $ignore;
        return $this;
    }

    public function getType(): string
    {
        return 'geo_distance';
    }

    public function apply(mixed $subject, string $direction): mixed
    {
        $sort = Sort::geoDistance($this->property, $this->lat, $this->lon)
            ->order($direction)
            ->unit($this->unit);

        if ($this->mode !== null) {
            $sort->mode($this->mode);
        }

        if ($this->distanceType !== null) {
            $sort->distanceType($this->distanceType);
        }

        if ($this->ignoreUnmapped !== null) {
            $sort->ignoreUnmapped($this->ignoreUnmapped);
        }

        $subject->sort($sort);

        return $subject;
    }
}
