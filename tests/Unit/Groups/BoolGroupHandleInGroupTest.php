<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Groups;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Exceptions\UnsupportedFilterInGroupException;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;

/**
 * Tests for handleInGroup() functionality in groups.
 *
 * @group unit
 * @group group
 */
class BoolGroupHandleInGroupTest extends UnitTestCase
{
    /** @test */
    public function exists_filter_with_false_uses_must_not_inside_group(): void
    {
        $group = ElasticGroup::bool('advanced')->children([
            ElasticFilter::exists('email'),
        ]);

        $query = $group->buildGroupQuery([
            'email' => false,
        ]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        // When exists filter is false, it should use must_not
        $this->assertArrayHasKey('bool', $array);
        $this->assertArrayHasKey('must_not', $array['bool']);
        $this->assertCount(1, $array['bool']['must_not']);
        $this->assertArrayHasKey('exists', $array['bool']['must_not'][0]);
    }

    /** @test */
    public function exists_filter_with_true_uses_filter_inside_group(): void
    {
        $group = ElasticGroup::bool('advanced')->children([
            ElasticFilter::exists('email'),
        ]);

        $query = $group->buildGroupQuery([
            'email' => true,
        ]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        // When exists filter is true, it should use filter (default clause)
        $this->assertArrayHasKey('bool', $array);
        $this->assertArrayHasKey('filter', $array['bool']);
        $this->assertCount(1, $array['bool']['filter']);
        $this->assertArrayHasKey('exists', $array['bool']['filter'][0]);
    }

    /** @test */
    public function null_filter_with_true_uses_must_not_inside_group(): void
    {
        $group = ElasticGroup::bool('advanced')->children([
            ElasticFilter::null('email'),
        ]);

        $query = $group->buildGroupQuery([
            'email' => true,
        ]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        // When null filter is true (is null), it should use must_not
        $this->assertArrayHasKey('bool', $array);
        $this->assertArrayHasKey('must_not', $array['bool']);
        $this->assertCount(1, $array['bool']['must_not']);
        $this->assertArrayHasKey('exists', $array['bool']['must_not'][0]);
    }

    /** @test */
    public function null_filter_with_false_uses_filter_inside_group(): void
    {
        $group = ElasticGroup::bool('advanced')->children([
            ElasticFilter::null('email'),
        ]);

        $query = $group->buildGroupQuery([
            'email' => false,
        ]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        // When null filter is false (is not null), it should use filter
        $this->assertArrayHasKey('bool', $array);
        $this->assertArrayHasKey('filter', $array['bool']);
        $this->assertCount(1, $array['bool']['filter']);
        $this->assertArrayHasKey('exists', $array['bool']['filter'][0]);
    }

    /** @test */
    public function trashed_filter_throws_exception_inside_group(): void
    {
        $group = ElasticGroup::bool('advanced')->children([
            ElasticFilter::trashed(),
        ]);

        $this->expectException(UnsupportedFilterInGroupException::class);
        $this->expectExceptionMessage('cannot be used inside group');

        $group->buildGroupQuery([
            'trashed' => 'with',
        ]);
    }

    /** @test */
    public function callback_filter_throws_exception_inside_group(): void
    {
        // Create an Eloquent callback filter (not supported in groups)
        $callbackFilter = EloquentFilter::callback('custom', function ($query, $value) {
            return $query;
        });

        $group = ElasticGroup::bool('advanced')->children([
            $callbackFilter,
        ]);

        $this->expectException(UnsupportedFilterInGroupException::class);
        $this->expectExceptionMessage('cannot be used inside group');

        $group->buildGroupQuery([
            'custom' => 'value',
        ]);
    }

    /** @test */
    public function passthrough_filter_throws_exception_inside_group(): void
    {
        // Create an Eloquent passthrough filter (not supported in groups)
        $passthroughFilter = EloquentFilter::passthrough('custom');

        $group = ElasticGroup::bool('advanced')->children([
            $passthroughFilter,
        ]);

        $this->expectException(UnsupportedFilterInGroupException::class);
        $this->expectExceptionMessage('cannot be used inside group');

        $group->buildGroupQuery([
            'custom' => 'value',
        ]);
    }

    /** @test */
    public function exists_filter_with_must_clause_uses_must_inside_group(): void
    {
        $group = ElasticGroup::bool('advanced')->children([
            ElasticFilter::exists('email')->inMust(),
        ]);

        $query = $group->buildGroupQuery([
            'email' => true,
        ]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        // When exists filter is set to must and value is true, it should use must
        $this->assertArrayHasKey('bool', $array);
        $this->assertArrayHasKey('must', $array['bool']);
        $this->assertCount(1, $array['bool']['must']);
        $this->assertArrayHasKey('exists', $array['bool']['must'][0]);
    }

    /** @test */
    public function mixed_filters_work_correctly_inside_group(): void
    {
        $group = ElasticGroup::bool('advanced')->children([
            ElasticFilter::term('status'),
            ElasticFilter::exists('email'),
            ElasticFilter::null('phone'),
        ]);

        $query = $group->buildGroupQuery([
            'status' => 'active',
            'email' => true,
            'phone' => true,
        ]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('bool', $array);

        // status=active -> filter (term)
        // email=true -> filter (exists)
        // phone=true -> must_not (null filter with true means IS NULL)
        $this->assertArrayHasKey('filter', $array['bool']);
        $this->assertArrayHasKey('must_not', $array['bool']);

        // 2 filters: term and exists
        $this->assertCount(2, $array['bool']['filter']);
        // 1 must_not: null filter
        $this->assertCount(1, $array['bool']['must_not']);
    }
}
