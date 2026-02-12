<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticFilter;
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
use Jackardios\QueryWizard\Filters\PassthroughFilter;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 * @group factory
 */
class ElasticFilterFactoryTest extends TestCase
{
    /** @test */
    public function term_creates_term_filter(): void
    {
        $filter = ElasticFilter::term('field', 'alias');

        $this->assertInstanceOf(TermFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function match_creates_match_filter(): void
    {
        $filter = ElasticFilter::match('field', 'alias');

        $this->assertInstanceOf(MatchFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function range_creates_range_filter(): void
    {
        $filter = ElasticFilter::range('field', 'alias');

        $this->assertInstanceOf(RangeFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function exists_creates_exists_filter(): void
    {
        $filter = ElasticFilter::exists('field', 'alias');

        $this->assertInstanceOf(ExistsFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function multi_match_creates_multi_match_filter(): void
    {
        $filter = ElasticFilter::multiMatch(['field1', 'field2'], 'search', 'alias');

        $this->assertInstanceOf(MultiMatchFilter::class, $filter);
        $this->assertEquals('search', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function geo_bounding_box_creates_geo_bounding_box_filter(): void
    {
        $filter = ElasticFilter::geoBoundingBox('location', 'bbox');

        $this->assertInstanceOf(GeoBoundingBoxFilter::class, $filter);
        $this->assertEquals('location', $filter->getProperty());
        $this->assertEquals('bbox', $filter->getName());
    }

    /** @test */
    public function geo_distance_creates_geo_distance_filter(): void
    {
        $filter = ElasticFilter::geoDistance('location', 'distance');

        $this->assertInstanceOf(GeoDistanceFilter::class, $filter);
        $this->assertEquals('location', $filter->getProperty());
        $this->assertEquals('distance', $filter->getName());
    }

    /** @test */
    public function wildcard_creates_wildcard_filter(): void
    {
        $filter = ElasticFilter::wildcard('field', 'alias');

        $this->assertInstanceOf(WildcardFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function prefix_creates_prefix_filter(): void
    {
        $filter = ElasticFilter::prefix('field', 'alias');

        $this->assertInstanceOf(PrefixFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function fuzzy_creates_fuzzy_filter(): void
    {
        $filter = ElasticFilter::fuzzy('field', 'alias');

        $this->assertInstanceOf(FuzzyFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function regexp_creates_regexp_filter(): void
    {
        $filter = ElasticFilter::regexp('field', 'alias');

        $this->assertInstanceOf(RegexpFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function ids_creates_ids_filter(): void
    {
        $filter = ElasticFilter::ids('field', 'alias');

        $this->assertInstanceOf(IdsFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function match_phrase_creates_match_phrase_filter(): void
    {
        $filter = ElasticFilter::matchPhrase('field', 'alias');

        $this->assertInstanceOf(MatchPhraseFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function match_phrase_prefix_creates_match_phrase_prefix_filter(): void
    {
        $filter = ElasticFilter::matchPhrasePrefix('field', 'alias');

        $this->assertInstanceOf(MatchPhrasePrefixFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function query_string_creates_query_string_filter(): void
    {
        $filter = ElasticFilter::queryString('field', 'alias');

        $this->assertInstanceOf(QueryStringFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function simple_query_string_creates_simple_query_string_filter(): void
    {
        $filter = ElasticFilter::simpleQueryString('field', 'alias');

        $this->assertInstanceOf(SimpleQueryStringFilter::class, $filter);
        $this->assertEquals('field', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function trashed_creates_trashed_filter(): void
    {
        $filter = ElasticFilter::trashed('alias');

        $this->assertInstanceOf(TrashedFilter::class, $filter);
        $this->assertEquals('trashed', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function callback_creates_callback_filter(): void
    {
        $callback = fn() => null;
        $filter = ElasticFilter::callback('name', $callback, 'alias');

        $this->assertInstanceOf(CallbackFilter::class, $filter);
        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function passthrough_creates_passthrough_filter(): void
    {
        $filter = ElasticFilter::passthrough('name', 'alias');

        $this->assertInstanceOf(PassthroughFilter::class, $filter);
        $this->assertEquals('name', $filter->getProperty());
        $this->assertEquals('alias', $filter->getName());
    }

    /** @test */
    public function date_range_creates_date_range_filter(): void
    {
        $filter = ElasticFilter::dateRange('created_at', 'date');

        $this->assertInstanceOf(DateRangeFilter::class, $filter);
        $this->assertEquals('created_at', $filter->getProperty());
        $this->assertEquals('date', $filter->getName());
    }

    /** @test */
    public function null_creates_null_filter(): void
    {
        $filter = ElasticFilter::null('deleted_at', 'deleted');

        $this->assertInstanceOf(NullFilter::class, $filter);
        $this->assertEquals('deleted_at', $filter->getProperty());
        $this->assertEquals('deleted', $filter->getName());
    }

    /** @test */
    public function nested_creates_nested_filter(): void
    {
        $filter = ElasticFilter::nested('comments', 'author', 'comment_author');

        $this->assertInstanceOf(NestedFilter::class, $filter);
        $this->assertEquals('author', $filter->getProperty());
        $this->assertEquals('comment_author', $filter->getName());
    }

    /** @test */
    public function geo_shape_creates_geo_shape_filter(): void
    {
        $filter = ElasticFilter::geoShape('boundary', 'area');

        $this->assertInstanceOf(GeoShapeFilter::class, $filter);
        $this->assertEquals('boundary', $filter->getProperty());
        $this->assertEquals('area', $filter->getName());
    }

    /** @test */
    public function more_like_this_creates_more_like_this_filter(): void
    {
        $filter = ElasticFilter::moreLikeThis(['title', 'body'], 'similar', 'related');

        $this->assertInstanceOf(MoreLikeThisFilter::class, $filter);
        $this->assertEquals('similar', $filter->getProperty());
        $this->assertEquals('related', $filter->getName());
    }
}
