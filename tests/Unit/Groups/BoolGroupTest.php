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
class BoolGroupTest extends UnitTestCase
{
    /** @test */
    public function it_can_be_created_via_factory(): void
    {
        $group = ElasticGroup::bool('advanced');

        $this->assertEquals('advanced', $group->getName());
        $this->assertEquals('bool_group', $group->getType());
    }

    /** @test */
    public function it_defaults_to_filter_clause(): void
    {
        $group = ElasticGroup::bool('advanced');

        $this->assertEquals(BoolClause::FILTER, $group->getEffectiveClause());
    }

    /** @test */
    public function it_can_be_set_to_must_clause(): void
    {
        $group = ElasticGroup::bool('advanced')->inMust();

        $this->assertEquals(BoolClause::MUST, $group->getEffectiveClause());
    }

    /** @test */
    public function it_accepts_children(): void
    {
        $children = [
            ElasticFilter::term('status', 'status'),
            ElasticFilter::term('priority', 'priority'),
        ];

        $group = ElasticGroup::bool('advanced')->children($children);

        $this->assertCount(2, $group->getChildren());
        $this->assertEquals($children, $group->getChildren());
    }

    /** @test */
    public function it_returns_child_filter_names(): void
    {
        $group = ElasticGroup::bool('advanced')->children([
            ElasticFilter::term('status', 'status'),
            ElasticFilter::term('priority', 'priority'),
        ]);

        $names = $group->getChildFilterNames();

        $this->assertEquals(['status', 'priority'], $names);
    }

    /** @test */
    public function it_returns_nested_child_filter_names(): void
    {
        $innerGroup = ElasticGroup::bool('inner')->children([
            ElasticFilter::term('a', 'a'),
            ElasticFilter::term('b', 'b'),
        ]);

        $outerGroup = ElasticGroup::bool('outer')->children([
            ElasticFilter::term('c', 'c'),
            $innerGroup,
        ]);

        $names = $outerGroup->getChildFilterNames();

        // Only leaf filter names, NOT group names ('inner' is excluded)
        $this->assertContains('c', $names);
        $this->assertContains('a', $names);
        $this->assertContains('b', $names);
        $this->assertNotContains('inner', $names);
        $this->assertCount(3, $names);
    }

    /** @test */
    public function it_accepts_minimum_should_match(): void
    {
        $group = ElasticGroup::bool('advanced')
            ->minimumShouldMatch(1)
            ->children([
                ElasticFilter::term('status', 'status')->inShould(),
                ElasticFilter::term('priority', 'priority')->inShould(),
            ]);

        $query = $group->buildGroupQuery([
            'status' => 'active',
            'priority' => 'high',
        ]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('bool', $array);
        $this->assertArrayHasKey('minimum_should_match', $array['bool']);
        $this->assertEquals(1, $array['bool']['minimum_should_match']);
    }

    /** @test */
    public function it_builds_bool_query_with_should_children(): void
    {
        $group = ElasticGroup::bool('advanced')
            ->minimumShouldMatch(1)
            ->children([
                ElasticFilter::term('status', 'status')->inShould(),
                ElasticFilter::term('priority', 'priority')->inShould(),
            ]);

        $query = $group->buildGroupQuery([
            'status' => 'active',
            'priority' => 'high',
        ]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('bool', $array);
        $this->assertArrayHasKey('should', $array['bool']);
        $this->assertCount(2, $array['bool']['should']);
    }

    /** @test */
    public function it_returns_null_when_no_child_values(): void
    {
        $group = ElasticGroup::bool('advanced')->children([
            ElasticFilter::term('status', 'status'),
        ]);

        $query = $group->buildGroupQuery([]);

        $this->assertNull($query);
    }
}
