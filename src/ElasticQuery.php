<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use BadMethodCallException;
use Closure;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\Compound\BoostingQuery;
use Jackardios\EsScoutDriver\Query\Compound\ConstantScoreQuery;
use Jackardios\EsScoutDriver\Query\Compound\DisMaxQuery;
use Jackardios\EsScoutDriver\Query\Compound\FunctionScoreQuery;
use Jackardios\EsScoutDriver\Query\Compound\NestedQuery;
use Jackardios\EsScoutDriver\Query\FullText\CombinedFieldsQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchPhrasePrefixQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchPhraseQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use Jackardios\EsScoutDriver\Query\FullText\MultiMatchQuery;
use Jackardios\EsScoutDriver\Query\FullText\QueryStringQuery;
use Jackardios\EsScoutDriver\Query\FullText\SimpleQueryStringQuery;
use Jackardios\EsScoutDriver\Query\Geo\GeoBoundingBoxQuery;
use Jackardios\EsScoutDriver\Query\Geo\GeoDistanceQuery;
use Jackardios\EsScoutDriver\Query\Geo\GeoShapeQuery;
use Jackardios\EsScoutDriver\Query\Joining\HasChildQuery;
use Jackardios\EsScoutDriver\Query\Joining\HasParentQuery;
use Jackardios\EsScoutDriver\Query\Joining\ParentIdQuery;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Query\RawQuery;
use Jackardios\EsScoutDriver\Query\Specialized\KnnQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MatchAllQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MatchNoneQuery;
use Jackardios\EsScoutDriver\Query\Specialized\MoreLikeThisQuery;
use Jackardios\EsScoutDriver\Query\Specialized\PinnedQuery;
use Jackardios\EsScoutDriver\Query\Specialized\ScriptScoreQuery;
use Jackardios\EsScoutDriver\Query\Specialized\SemanticQuery;
use Jackardios\EsScoutDriver\Query\Specialized\SparseVectorQuery;
use Jackardios\EsScoutDriver\Query\Specialized\TextExpansionQuery;
use Jackardios\EsScoutDriver\Query\Term\ExistsQuery;
use Jackardios\EsScoutDriver\Query\Term\FuzzyQuery;
use Jackardios\EsScoutDriver\Query\Term\IdsQuery;
use Jackardios\EsScoutDriver\Query\Term\PrefixQuery;
use Jackardios\EsScoutDriver\Query\Term\RangeQuery;
use Jackardios\EsScoutDriver\Query\Term\RegexpQuery;
use Jackardios\EsScoutDriver\Query\Term\TermQuery;
use Jackardios\EsScoutDriver\Query\Term\TermsQuery;
use Jackardios\EsScoutDriver\Query\Term\WildcardQuery;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * Proxy for es-scout-driver Query DSL inside elastic-query-wizard namespace.
 *
 * Forwards every factory of `Jackardios\EsScoutDriver\Support\Query` and its macros.
 *
 * @method static RawQuery raw(array<string, mixed> $query)
 * @method static TermQuery term(string $field, string|int|float|bool $value)
 * @method static TermsQuery terms(string $field, array<int, string|int|float|bool> $values)
 * @method static RangeQuery range(string $field)
 * @method static ExistsQuery exists(string $field)
 * @method static PrefixQuery prefix(string $field, string $value)
 * @method static WildcardQuery wildcard(string $field, string $value)
 * @method static RegexpQuery regexp(string $field, string $value)
 * @method static FuzzyQuery fuzzy(string $field, string $value)
 * @method static IdsQuery ids(array<int, string> $values)
 * @method static MatchQuery match(string $field, string|int|float|bool $query)
 * @method static MultiMatchQuery multiMatch(array<int, string> $fields, string|int|float|bool $query)
 * @method static CombinedFieldsQuery combinedFields(array<int, string> $fields, string $query)
 * @method static MatchPhraseQuery matchPhrase(string $field, string $query)
 * @method static MatchPhrasePrefixQuery matchPhrasePrefix(string $field, string $query)
 * @method static QueryStringQuery queryString(string $query)
 * @method static SimpleQueryStringQuery simpleQueryString(string $query)
 * @method static GeoDistanceQuery geoDistance(string $field, float $lat, float $lon, string $distance)
 * @method static GeoBoundingBoxQuery geoBoundingBox(string $field, float $topLeftLat, float $topLeftLon, float $bottomRightLat, float $bottomRightLon)
 * @method static GeoShapeQuery geoShape(string $field)
 * @method static MatchAllQuery matchAll()
 * @method static MatchNoneQuery matchNone()
 * @method static MoreLikeThisQuery moreLikeThis(array<int, string> $fields, string|array<int, string|array<string, mixed>> $like)
 * @method static ScriptScoreQuery scriptScore(QueryInterface|array<string, mixed> $query, array<string, mixed> $script)
 * @method static BoolQuery bool()
 * @method static NestedQuery nested(string $path, QueryInterface|Closure|array<string, mixed> $query)
 * @method static FunctionScoreQuery functionScore(QueryInterface|array<string, mixed>|null $query = null)
 * @method static DisMaxQuery disMax(array<QueryInterface|array<string, mixed>> $queries = [])
 * @method static BoostingQuery boosting(QueryInterface|array<string, mixed> $positive, QueryInterface|array<string, mixed> $negative)
 * @method static ConstantScoreQuery constantScore(QueryInterface|Closure|array<string, mixed> $filter)
 * @method static HasChildQuery hasChild(string $type, QueryInterface|Closure|array<string, mixed> $query)
 * @method static HasParentQuery hasParent(string $parentType, QueryInterface|Closure|array<string, mixed> $query)
 * @method static ParentIdQuery parentId(string $type, string $id)
 * @method static KnnQuery knn(string $field, array<int, float> $queryVector, int $k)
 * @method static SparseVectorQuery sparseVector(string $field)
 * @method static PinnedQuery pinned(QueryInterface|array<string, mixed> $organic)
 * @method static SemanticQuery semantic(string $field, string $query)
 * @method static TextExpansionQuery textExpansion(string $field, string $modelId)
 */
final class ElasticQuery
{
    /**
     * @param  array<int|string, mixed>  $arguments
     *
     * @throws BadMethodCallException When Query has no such factory or macro
     */
    public static function __callStatic(string $name, array $arguments): mixed
    {
        if (! method_exists(Query::class, $name) && ! Query::hasMacro($name)) {
            throw new BadMethodCallException(sprintf('Call to undefined method %s::%s()', self::class, $name));
        }

        return Query::$name(...$arguments);
    }
}
