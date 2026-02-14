<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Groups;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group group
 */
class NestedGroupTest extends UnitTestCase
{
    /** @test */
    public function it_can_be_created_via_factory(): void
    {
        $group = ElasticGroup::nested('sides');

        $this->assertEquals('sides', $group->getName());
        $this->assertEquals('sides', $group->getPath());
        $this->assertEquals('nested_group', $group->getType());
    }

    /** @test */
    public function it_defaults_to_filter_clause(): void
    {
        $group = ElasticGroup::nested('sides');

        $this->assertEquals(BoolClause::FILTER, $group->getEffectiveClause());
    }

    /** @test */
    public function it_can_be_set_to_must_clause(): void
    {
        $group = ElasticGroup::nested('sides')->inMust();

        $this->assertEquals(BoolClause::MUST, $group->getEffectiveClause());
    }

    /** @test */
    public function it_accepts_children(): void
    {
        $children = [
            ElasticFilter::term('sides.id', 'id'),
            ElasticFilter::match('sides.address', 'search'),
        ];

        $group = ElasticGroup::nested('sides')->children($children);

        $this->assertCount(2, $group->getChildren());
    }

    /** @test */
    public function it_accepts_score_mode(): void
    {
        $group = ElasticGroup::nested('sides')
            ->scoreMode('avg')
            ->children([
                ElasticFilter::term('sides.id', 'id'),
            ]);

        $query = $group->buildGroupQuery(['id' => '123']);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('nested', $array);
        $this->assertArrayHasKey('score_mode', $array['nested']);
        $this->assertEquals('avg', $array['nested']['score_mode']);
    }

    /** @test */
    public function it_accepts_ignore_unmapped(): void
    {
        $group = ElasticGroup::nested('sides')
            ->ignoreUnmapped()
            ->children([
                ElasticFilter::term('sides.id', 'id'),
            ]);

        $query = $group->buildGroupQuery(['id' => '123']);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('nested', $array);
        $this->assertArrayHasKey('ignore_unmapped', $array['nested']);
        $this->assertTrue($array['nested']['ignore_unmapped']);
    }

    /** @test */
    public function it_builds_nested_query_with_inner_bool(): void
    {
        $group = ElasticGroup::nested('sides')->children([
            ElasticFilter::term('sides.id', 'id'),
            ElasticFilter::match('sides.address', 'search')->inMust(),
        ]);

        $query = $group->buildGroupQuery([
            'id' => '123',
            'search' => 'Main Street',
        ]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('nested', $array);
        $this->assertEquals('sides', $array['nested']['path']);
        $this->assertArrayHasKey('query', $array['nested']);
        $this->assertArrayHasKey('bool', $array['nested']['query']);
    }

    /** @test */
    public function it_returns_null_when_no_child_values(): void
    {
        $group = ElasticGroup::nested('sides')->children([
            ElasticFilter::term('sides.id', 'id'),
        ]);

        $query = $group->buildGroupQuery([]);

        $this->assertNull($query);
    }

    /** @test */
    public function it_can_use_alias(): void
    {
        $group = ElasticGroup::nested('sides', 'side_filters');

        $this->assertEquals('side_filters', $group->getName());
        $this->assertEquals('sides', $group->getPath());
    }

    /** @test */
    public function it_returns_score_mode_via_getter(): void
    {
        $group = ElasticGroup::nested('sides')->scoreMode('max');

        $this->assertEquals('max', $group->getScoreMode());
    }

    /** @test */
    public function it_returns_ignore_unmapped_via_getter(): void
    {
        $group = ElasticGroup::nested('sides')->ignoreUnmapped();

        $this->assertTrue($group->getIgnoreUnmapped());
    }

    /** @test */
    public function it_accepts_inner_hits_with_defaults(): void
    {
        $group = ElasticGroup::nested('sides')
            ->innerHits()
            ->children([
                ElasticFilter::term('sides.id', 'id'),
            ]);

        $this->assertEquals([], $group->getInnerHits());

        $query = $group->buildGroupQuery(['id' => '123']);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('nested', $array);
        $this->assertArrayHasKey('inner_hits', $array['nested']);
    }

    /** @test */
    public function it_accepts_inner_hits_with_options(): void
    {
        $group = ElasticGroup::nested('comments')
            ->innerHits([
                'name' => 'matched_comments',
                'size' => 5,
                'from' => 0,
                'sort' => [['date' => ['order' => 'desc']]],
            ])
            ->children([
                ElasticFilter::term('comments.status', 'status'),
            ]);

        $query = $group->buildGroupQuery(['status' => 'approved']);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('nested', $array);
        $this->assertArrayHasKey('inner_hits', $array['nested']);
        $this->assertEquals('matched_comments', $array['nested']['inner_hits']['name']);
        $this->assertEquals(5, $array['nested']['inner_hits']['size']);
        $this->assertEquals(0, $array['nested']['inner_hits']['from']);
        $this->assertArrayHasKey('sort', $array['nested']['inner_hits']);
    }

    /** @test */
    public function it_combines_all_nested_options(): void
    {
        $group = ElasticGroup::nested('reviews')
            ->scoreMode('avg')
            ->ignoreUnmapped()
            ->innerHits(['size' => 3])
            ->inMust()
            ->children([
                ElasticFilter::range('reviews.rating', 'min_rating'),
            ]);

        $this->assertEquals('avg', $group->getScoreMode());
        $this->assertTrue($group->getIgnoreUnmapped());
        $this->assertEquals(['size' => 3], $group->getInnerHits());

        $query = $group->buildGroupQuery(['min_rating' => ['gte' => 4]]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertEquals('reviews', $array['nested']['path']);
        $this->assertEquals('avg', $array['nested']['score_mode']);
        $this->assertTrue($array['nested']['ignore_unmapped']);
        $this->assertEquals(['size' => 3], $array['nested']['inner_hits']);
    }

    /** @test */
    public function it_returns_null_defaults_for_getters(): void
    {
        $group = ElasticGroup::nested('sides');

        $this->assertNull($group->getScoreMode());
        $this->assertNull($group->getIgnoreUnmapped());
        $this->assertNull($group->getInnerHits());
    }

    /** @test */
    public function it_accepts_ignore_unmapped_false(): void
    {
        $group = ElasticGroup::nested('sides')
            ->ignoreUnmapped(false)
            ->children([
                ElasticFilter::term('sides.id', 'id'),
            ]);

        $this->assertFalse($group->getIgnoreUnmapped());

        $query = $group->buildGroupQuery(['id' => '123']);
        $array = $query->toArray();

        $this->assertArrayHasKey('ignore_unmapped', $array['nested']);
        $this->assertFalse($array['nested']['ignore_unmapped']);
    }

    /** @test */
    public function it_does_not_include_inner_hits_when_null(): void
    {
        $group = ElasticGroup::nested('sides')
            ->children([
                ElasticFilter::term('sides.id', 'id'),
            ]);

        $query = $group->buildGroupQuery(['id' => '123']);
        $array = $query->toArray();

        $this->assertArrayNotHasKey('inner_hits', $array['nested']);
    }
}
