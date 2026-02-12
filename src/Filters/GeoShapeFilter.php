<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoShapeValue;
use Jackardios\EsScoutDriver\Query\Geo\GeoShapeQuery;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Filter documents by geographic shape relationships.
 *
 * Supports envelope, polygon, point, circle, and indexed_shape types.
 *
 * Note: Circle type requires Elasticsearch 6.6+.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-shape-query.html
 */
class GeoShapeFilter extends AbstractElasticFilter
{
    protected ?string $relation = null;

    protected ?bool $ignoreUnmapped = null;

    public static function make(string $property, ?string $alias = null): static
    {
        return new static($property, $alias);
    }

    /**
     * Set the spatial relation for the query.
     *
     * @param string $relation One of: 'intersects', 'disjoint', 'within', 'contains'
     */
    public function relation(string $relation): static
    {
        $this->relation = $relation;
        return $this;
    }

    /**
     * Ignore the query if the field is unmapped.
     */
    public function ignoreUnmapped(bool $ignore = true): static
    {
        $this->ignoreUnmapped = $ignore;
        return $this;
    }

    public function getType(): string
    {
        return 'geo_shape';
    }

    public function handle(SearchBuilder $builder, mixed $value): void
    {
        if (empty($value) || !is_array($value)) {
            return;
        }

        $query = Query::geoShape($this->property);

        $this->applyShape($query, $value);

        if ($this->relation !== null) {
            $query->relation($this->relation);
        }

        if ($this->ignoreUnmapped !== null) {
            $query->ignoreUnmapped($this->ignoreUnmapped);
        }

        $builder->filter($query);
    }

    /**
     * @param array<string, mixed> $value
     */
    protected function applyShape(GeoShapeQuery $query, array $value): void
    {
        $type = $value['type'] ?? null;

        match ($type) {
            'envelope' => $this->applyEnvelope($query, $value),
            'polygon' => $this->applyPolygon($query, $value),
            'point' => $this->applyPoint($query, $value),
            'circle' => $this->applyCircle($query, $value),
            'indexed_shape' => $this->applyIndexedShape($query, $value),
            default => throw InvalidGeoShapeValue::unknownType($this->property, $type),
        };
    }

    /**
     * @param array<string, mixed> $value
     */
    protected function applyEnvelope(GeoShapeQuery $query, array $value): void
    {
        $coordinates = $value['coordinates'] ?? null;

        if (!is_array($coordinates) || count($coordinates) !== 2) {
            throw InvalidGeoShapeValue::invalidEnvelope($this->property);
        }

        $query->envelope($coordinates);
    }

    /**
     * @param array<string, mixed> $value
     */
    protected function applyPolygon(GeoShapeQuery $query, array $value): void
    {
        $coordinates = $value['coordinates'] ?? null;

        if (!is_array($coordinates) || $coordinates === []) {
            throw InvalidGeoShapeValue::invalidPolygon($this->property);
        }

        $query->polygon($coordinates);
    }

    /**
     * @param array<string, mixed> $value
     */
    protected function applyPoint(GeoShapeQuery $query, array $value): void
    {
        $coordinates = $value['coordinates'] ?? null;

        if (!is_array($coordinates) || count($coordinates) !== 2) {
            throw InvalidGeoShapeValue::invalidPoint($this->property);
        }

        $query->point($coordinates);
    }

    /**
     * Apply circle shape (requires Elasticsearch 6.6+).
     *
     * @param array<string, mixed> $value
     */
    protected function applyCircle(GeoShapeQuery $query, array $value): void
    {
        $coordinates = $value['coordinates'] ?? null;
        $radius = $value['radius'] ?? null;

        if (!is_array($coordinates) || count($coordinates) !== 2 || !is_string($radius)) {
            throw InvalidGeoShapeValue::invalidCircle($this->property);
        }

        $query->circle((float) $coordinates[0], (float) $coordinates[1], $radius);
    }

    /**
     * @param array<string, mixed> $value
     */
    protected function applyIndexedShape(GeoShapeQuery $query, array $value): void
    {
        $index = $value['index'] ?? null;
        $id = $value['id'] ?? null;
        $path = $value['path'] ?? 'shape';

        if (!is_string($index) || !is_string($id)) {
            throw InvalidGeoShapeValue::invalidIndexedShape($this->property);
        }

        $query->indexedShape($index, $id, $path);
    }
}
