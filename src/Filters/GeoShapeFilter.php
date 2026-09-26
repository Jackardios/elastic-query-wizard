<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Filters;

use Jackardios\ElasticQueryWizard\Exceptions\InvalidGeoShapeValue;
use Jackardios\ElasticQueryWizard\FilterValueSanitizer;
use Jackardios\EsScoutDriver\Query\Geo\GeoShapeQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Filter documents by geographic shape relationships.
 *
 * Supports envelope, polygon, point, and indexed_shape types.
 *
 * Note: Circle type is not supported as an inline shape in ES 8.x/9.x.
 * For radius-based filtering, use GeoDistanceFilter instead.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-geo-shape-query.html
 */
final class GeoShapeFilter extends AbstractElasticFilter
{
    protected ?string $relation = null;

    protected ?bool $ignoreUnmapped = null;

    public static function make(string $property, ?string $alias = null): static
    {
        return new self($property, $alias);
    }

    /**
     * Set the spatial relation for the query.
     *
     * @param  string  $relation  One of: 'intersects', 'disjoint', 'within', 'contains'
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

    public function buildQuery(mixed $value): ?QueryInterface
    {
        if (empty($value) || ! is_array($value)) {
            return null;
        }

        /** @var array<string, mixed> $shapeValue */
        $shapeValue = $value;

        $query = Query::geoShape($this->property);

        $this->applyShape($query, $shapeValue);

        if ($this->relation !== null) {
            $query->relation($this->relation);
        }

        if ($this->ignoreUnmapped !== null) {
            $query->ignoreUnmapped($this->ignoreUnmapped);
        }

        return $query;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function applyShape(GeoShapeQuery $query, array $value): void
    {
        $type = $value['type'] ?? null;
        $typeString = is_string($type) ? $type : null;

        match ($typeString) {
            'envelope' => $this->applyEnvelope($query, $value),
            'polygon' => $this->applyPolygon($query, $value),
            'point' => $this->applyPoint($query, $value),
            'indexed_shape' => $this->applyIndexedShape($query, $value),
            default => throw InvalidGeoShapeValue::unknownType($this->property, $typeString),
        };
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function applyEnvelope(GeoShapeQuery $query, array $value): void
    {
        $coordinates = $value['coordinates'] ?? null;

        if (! is_array($coordinates) || count($coordinates) !== 2) {
            throw InvalidGeoShapeValue::invalidEnvelope($this->property);
        }

        $validated = FilterValueSanitizer::toCoordinatesArray($coordinates);

        if ($validated === null) {
            throw InvalidGeoShapeValue::invalidEnvelope($this->property);
        }

        $query->envelope($validated);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function applyPolygon(GeoShapeQuery $query, array $value): void
    {
        $coordinates = $value['coordinates'] ?? null;

        if (! is_array($coordinates) || $coordinates === [] || ! array_is_list($coordinates)) {
            throw InvalidGeoShapeValue::invalidPolygon($this->property);
        }

        $rings = [];

        foreach ($coordinates as $ring) {
            $rings[] = $this->closedRing($ring) ?? throw InvalidGeoShapeValue::invalidPolygon($this->property);
        }

        $query->shape(['type' => 'polygon', 'coordinates' => $rings]);
    }

    /**
     * A GeoJSON linear ring, closed if its last point is not its first, or null
     * when it is not one: Elasticsearch requires four points once closed.
     *
     * @return array<int, array<int, float>>|null
     */
    private function closedRing(mixed $ring): ?array
    {
        $points = is_array($ring) ? FilterValueSanitizer::toCoordinatesArray($ring) : null;

        if ($points === null || $points === []) {
            return null;
        }

        if ($points[0] !== $points[count($points) - 1]) {
            $points[] = $points[0];
        }

        return count($points) >= 4 ? $points : null;
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function applyPoint(GeoShapeQuery $query, array $value): void
    {
        $coordinates = $value['coordinates'] ?? null;

        if (! is_array($coordinates) || count($coordinates) !== 2) {
            throw InvalidGeoShapeValue::invalidPoint($this->property);
        }

        $lon = FilterValueSanitizer::finiteFloat($coordinates[0] ?? null);
        $lat = FilterValueSanitizer::finiteFloat($coordinates[1] ?? null);

        if ($lon === null || $lat === null) {
            throw InvalidGeoShapeValue::invalidPoint($this->property);
        }

        $query->point([$lon, $lat]);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function applyIndexedShape(GeoShapeQuery $query, array $value): void
    {
        $index = $value['index'] ?? null;
        $id = $value['id'] ?? null;
        $rawPath = $value['path'] ?? null;
        $path = is_string($rawPath) ? $rawPath : 'shape';

        if (! is_string($index) || ! is_string($id)) {
            throw InvalidGeoShapeValue::invalidIndexedShape($this->property);
        }

        $query->indexedShape($index, $id, $path);
    }
}
