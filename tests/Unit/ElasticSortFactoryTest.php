<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticSort;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\Sorts\GeoDistanceSort;
use Jackardios\ElasticQueryWizard\Sorts\ScoreSort;
use Jackardios\ElasticQueryWizard\Sorts\ScriptSort;
use Jackardios\QueryWizard\Sorts\CallbackSort;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 * @group factory
 */
class ElasticSortFactoryTest extends TestCase
{
    /** @test */
    public function field_creates_field_sort(): void
    {
        $sort = ElasticSort::field('field', 'alias');

        $this->assertInstanceOf(FieldSort::class, $sort);
        $this->assertEquals('field', $sort->getProperty());
        $this->assertEquals('alias', $sort->getName());
    }

    /** @test */
    public function callback_creates_callback_sort(): void
    {
        $callback = fn() => null;
        $sort = ElasticSort::callback('name', $callback, 'alias');

        $this->assertInstanceOf(CallbackSort::class, $sort);
        $this->assertEquals('name', $sort->getProperty());
        $this->assertEquals('alias', $sort->getName());
    }

    /** @test */
    public function geo_distance_creates_geo_distance_sort(): void
    {
        $sort = ElasticSort::geoDistance('location', 55.75, 37.62, 'distance');

        $this->assertInstanceOf(GeoDistanceSort::class, $sort);
        $this->assertEquals('location', $sort->getProperty());
        $this->assertEquals('distance', $sort->getName());
    }

    /** @test */
    public function script_creates_script_sort(): void
    {
        $sort = ElasticSort::script("doc['price'].value", 'custom', 'alias');

        $this->assertInstanceOf(ScriptSort::class, $sort);
        $this->assertEquals('custom', $sort->getProperty());
        $this->assertEquals('alias', $sort->getName());
    }

    /** @test */
    public function score_creates_score_sort(): void
    {
        $sort = ElasticSort::score('relevance');

        $this->assertInstanceOf(ScoreSort::class, $sort);
        $this->assertEquals('_score', $sort->getProperty());
        $this->assertEquals('relevance', $sort->getName());
    }

    /** @test */
    public function score_without_alias_uses_score_as_name(): void
    {
        $sort = ElasticSort::score();

        $this->assertInstanceOf(ScoreSort::class, $sort);
        $this->assertEquals('_score', $sort->getProperty());
        $this->assertEquals('_score', $sort->getName());
    }
}
