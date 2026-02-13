<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Jackardios\QueryWizard\Filters\CallbackFilter;
use Jackardios\ElasticQueryWizard\Filters\DateRangeFilter;
use Jackardios\ElasticQueryWizard\Filters\ExistsFilter;
use Jackardios\ElasticQueryWizard\Filters\FuzzyFilter;
use Jackardios\ElasticQueryWizard\Filters\GeoBoundingBoxFilter;
use Jackardios\ElasticQueryWizard\Filters\GeoDistanceFilter;
use Jackardios\ElasticQueryWizard\Filters\GeoShapeFilter;
use Jackardios\ElasticQueryWizard\Filters\IdsFilter;
use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Filters\MatchPhraseFilter;
use Jackardios\ElasticQueryWizard\Filters\MatchPhrasePrefixFilter;
use Jackardios\ElasticQueryWizard\Filters\MoreLikeThisFilter;
use Jackardios\ElasticQueryWizard\Filters\MultiMatchFilter;
use Jackardios\ElasticQueryWizard\Filters\NestedFilter;
use Jackardios\ElasticQueryWizard\Filters\NullFilter;
use Jackardios\ElasticQueryWizard\Filters\PrefixFilter;
use Jackardios\ElasticQueryWizard\Filters\QueryStringFilter;
use Jackardios\ElasticQueryWizard\Filters\RangeFilter;
use Jackardios\ElasticQueryWizard\Filters\RegexpFilter;
use Jackardios\ElasticQueryWizard\Filters\SimpleQueryStringFilter;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Filters\TrashedFilter;
use Jackardios\ElasticQueryWizard\Filters\WildcardFilter;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Filters\PassthroughFilter;

final class ElasticFilter
{
    public static function term(string $property, ?string $alias = null): TermFilter
    {
        return TermFilter::make($property, $alias);
    }

    public static function match(string $property, ?string $alias = null): MatchFilter
    {
        return MatchFilter::make($property, $alias);
    }

    public static function range(string $property, ?string $alias = null): RangeFilter
    {
        return RangeFilter::make($property, $alias);
    }

    public static function exists(string $property, ?string $alias = null): ExistsFilter
    {
        return ExistsFilter::make($property, $alias);
    }

    /**
     * @param string[] $fields The Elasticsearch fields to search across
     */
    public static function multiMatch(array $fields, string $property, ?string $alias = null): MultiMatchFilter
    {
        return MultiMatchFilter::make($fields, $property, $alias);
    }

    public static function geoBoundingBox(string $property, ?string $alias = null): GeoBoundingBoxFilter
    {
        return GeoBoundingBoxFilter::make($property, $alias);
    }

    public static function geoDistance(string $property, ?string $alias = null): GeoDistanceFilter
    {
        return GeoDistanceFilter::make($property, $alias);
    }

    public static function geoShape(string $property, ?string $alias = null): GeoShapeFilter
    {
        return GeoShapeFilter::make($property, $alias);
    }

    public static function wildcard(string $property, ?string $alias = null): WildcardFilter
    {
        return WildcardFilter::make($property, $alias);
    }

    public static function prefix(string $property, ?string $alias = null): PrefixFilter
    {
        return PrefixFilter::make($property, $alias);
    }

    public static function fuzzy(string $property, ?string $alias = null): FuzzyFilter
    {
        return FuzzyFilter::make($property, $alias);
    }

    public static function regexp(string $property, ?string $alias = null): RegexpFilter
    {
        return RegexpFilter::make($property, $alias);
    }

    public static function ids(string $property, ?string $alias = null): IdsFilter
    {
        return IdsFilter::make($property, $alias);
    }

    public static function matchPhrase(string $property, ?string $alias = null): MatchPhraseFilter
    {
        return MatchPhraseFilter::make($property, $alias);
    }

    public static function matchPhrasePrefix(string $property, ?string $alias = null): MatchPhrasePrefixFilter
    {
        return MatchPhrasePrefixFilter::make($property, $alias);
    }

    public static function queryString(string $property, ?string $alias = null): QueryStringFilter
    {
        return QueryStringFilter::make($property, $alias);
    }

    public static function simpleQueryString(string $property, ?string $alias = null): SimpleQueryStringFilter
    {
        return SimpleQueryStringFilter::make($property, $alias);
    }

    public static function trashed(?string $alias = null): TrashedFilter
    {
        return TrashedFilter::make($alias);
    }

    public static function dateRange(string $property, ?string $alias = null): DateRangeFilter
    {
        return DateRangeFilter::make($property, $alias);
    }

    public static function null(string $property, ?string $alias = null): NullFilter
    {
        return NullFilter::make($property, $alias);
    }

    /**
     * @param string $path The nested document path (e.g., 'comments', 'variants')
     * @param string $property The field within the nested document to filter on
     */
    public static function nested(string $path, string $property, ?string $alias = null): NestedFilter
    {
        return NestedFilter::make($path, $property, $alias);
    }

    /**
     * Find similar documents based on text analysis.
     *
     * @param string[] $fields Fields to analyze for similarity
     */
    public static function moreLikeThis(array $fields, string $property, ?string $alias = null): MoreLikeThisFilter
    {
        return MoreLikeThisFilter::make($fields, $property, $alias);
    }

    /**
     * @param callable(SearchBuilder, mixed, string): mixed $callback
     */
    public static function callback(string $name, callable $callback, ?string $alias = null): CallbackFilter
    {
        return CallbackFilter::make($name, $callback, $alias);
    }

    /**
     * Creates a passthrough filter that works with the SearchBuilder directly.
     * Useful for non-elastic filtering needs.
     */
    public static function passthrough(string $name, ?string $alias = null): PassthroughFilter
    {
        return PassthroughFilter::make($name, $alias);
    }
}
