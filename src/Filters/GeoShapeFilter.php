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

    protected ?string $indexedShapeIndex = null;

    protected string $indexedShapePath = 'shape';

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

    /**
     * Accept `indexed_shape` values, which name a document of the given index
     * by `id`; the shape is read from the document's `path` field. The client
     * chooses only the document.
     */
    public function indexedShapes(string $index, string $path = 'shape'): static
    {
        $this->indexedShapeIndex = $index;
        $this->indexedShapePath = $path;

        return $this;
    }

    public function getType(): string
    {
        return 'geo_shape';
    }

    public function buildQuery(mixed $value): ?QueryInterface
    {
        if (FilterValueSanitizer::isBlank($value)) {
            return null;
        }

        if (! is_array($value)) {
            throw InvalidGeoShapeValue::unknownType($value, $this, null);
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
            default => throw InvalidGeoShapeValue::unknownType($value, $this, $typeString),
        };
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function applyEnvelope(GeoShapeQuery $query, array $value): void
    {
        $coordinates = $value['coordinates'] ?? null;

        if (! is_array($coordinates) || count($coordinates) !== 2) {
            throw InvalidGeoShapeValue::invalidEnvelope($value, $this);
        }

        $validated = FilterValueSanitizer::toCoordinatesArray($coordinates);

        if ($validated === null) {
            throw InvalidGeoShapeValue::invalidEnvelope($value, $this);
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
            throw InvalidGeoShapeValue::invalidPolygon($value, $this);
        }

        $rings = [];

        foreach ($coordinates as $ring) {
            $rings[] = $this->closedRing($ring) ?? throw InvalidGeoShapeValue::invalidPolygon($value, $this);
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
            throw InvalidGeoShapeValue::invalidPoint($value, $this);
        }

        $lon = FilterValueSanitizer::finiteFloat($coordinates[0] ?? null);
        $lat = FilterValueSanitizer::finiteFloat($coordinates[1] ?? null);

        if ($lon === null || $lat === null) {
            throw InvalidGeoShapeValue::invalidPoint($value, $this);
        }

        $query->point([$lon, $lat]);
    }

    /**
     * @param  array<string, mixed>  $value
     */
    protected function applyIndexedShape(GeoShapeQuery $query, array $value): void
    {
        if ($this->indexedShapeIndex === null) {
            throw InvalidGeoShapeValue::unknownType($value, $this, 'indexed_shape');
        }

        $id = $value['id'] ?? null;

        if (array_diff(array_keys($value), ['type', 'id']) !== [] || ! (is_string($id) || is_int($id)) || trim((string) $id) === '') {
            throw InvalidGeoShapeValue::invalidIndexedShape($value, $this);
        }

        $query->indexedShape($this->indexedShapeIndex, trim((string) $id), $this->indexedShapePath);
    }
}
