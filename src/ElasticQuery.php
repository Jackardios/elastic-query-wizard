<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use BadMethodCallException;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Proxy for es-scout-driver Query DSL inside elastic-query-wizard namespace.
 *
 * @method static mixed raw(array $query)
 * @method static mixed term(string $field, string|int|float|bool $value)
 * @method static mixed terms(string $field, array $values)
 * @method static mixed range(string $field)
 * @method static mixed exists(string $field)
 * @method static mixed prefix(string $field, string $value)
 * @method static mixed wildcard(string $field, string $value)
 * @method static mixed regexp(string $field, string $value)
 * @method static mixed fuzzy(string $field, string $value)
 * @method static mixed ids(array $values)
 * @method static mixed match(string $field, string|int|float|bool $query)
 * @method static mixed multiMatch(array $fields, string|int|float|bool $query)
 * @method static mixed matchPhrase(string $field, string $query)
 * @method static mixed matchPhrasePrefix(string $field, string $query)
 * @method static mixed queryString(string $query)
 * @method static mixed simpleQueryString(string $query)
 * @method static mixed geoDistance(string $field, float $lat, float $lon, string $distance)
 * @method static mixed geoBoundingBox(string $field, float $topLeftLat, float $topLeftLon, float $bottomRightLat, float $bottomRightLon)
 * @method static mixed geoShape(string $field)
 * @method static mixed matchAll()
 * @method static mixed matchNone()
 * @method static mixed moreLikeThis(array $fields, string|array $like)
 * @method static mixed scriptScore(mixed $query, array $script)
 * @method static mixed bool()
 * @method static mixed nested(string $path, mixed $query)
 * @method static mixed functionScore(mixed $query = null)
 * @method static mixed disMax(array $queries = [])
 * @method static mixed boosting(mixed $positive, mixed $negative)
 * @method static mixed constantScore(mixed $filter)
 * @method static mixed hasChild(string $type, mixed $query)
 * @method static mixed hasParent(string $parentType, mixed $query)
 * @method static mixed parentId(string $type, string $id)
 * @method static mixed knn(string $field, array $queryVector, int $k)
 * @method static mixed sparseVector(string $field)
 * @method static mixed pinned(mixed $organic)
 * @method static mixed semantic(string $field, string $query)
 * @method static mixed textExpansion(string $field, string $modelId)
 */
final class ElasticQuery
{
    /**
     * @param array<int, mixed> $arguments
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        if (!method_exists(Query::class, $name)) {
            throw new BadMethodCallException(sprintf('Method "%s::%s" does not exist.', self::class, $name));
        }

        return Query::$name(...$arguments);
    }
}
