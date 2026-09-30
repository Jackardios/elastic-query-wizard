<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Groups;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Exceptions\UnsupportedFilterInGroup;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for handleInGroup() functionality in groups.
 */
#[Group('unit')]
#[Group('group')]
class BoolGroupHandleInGroupTest extends UnitTestCase
{
    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
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

    #[Test]
    public function trashed_filter_is_refused_when_the_group_is_configured(): void
    {
        $this->expectException(UnsupportedFilterInGroup::class);
        $this->expectExceptionMessage('Filter `trashed` (Jackardios\\ElasticQueryWizard\\Filters\\TrashedFilter) cannot be used inside group `advanced`');

        ElasticGroup::bool('advanced')->children([ElasticFilter::trashed()]);
    }

    #[Test]
    public function callback_filter_is_refused_when_the_group_is_configured(): void
    {
        $this->expectException(UnsupportedFilterInGroup::class);
        $this->expectExceptionMessage('Filter `custom`');

        ElasticGroup::bool('advanced')->children([
            ElasticFilter::callback('custom', fn () => null),
        ]);
    }

    #[Test]
    public function passthrough_filter_is_refused_when_the_group_is_configured(): void
    {
        $this->expectException(UnsupportedFilterInGroup::class);
        $this->expectExceptionMessage('Filter `custom`');

        ElasticGroup::bool('advanced')->children([
            ElasticFilter::term('status'),
            EloquentFilter::passthrough('custom'),
        ]);
    }

    #[Test]
    public function an_unsupported_filter_in_a_nested_group_is_refused_when_that_group_is_configured(): void
    {
        $this->expectException(UnsupportedFilterInGroup::class);
        $this->expectExceptionMessage('cannot be used inside group `inner`');

        ElasticGroup::bool('outer')->children([
            ElasticGroup::bool('inner')->children([EloquentFilter::callback('custom', fn () => null)]),
        ]);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function a_missing_field_in_an_or_group_is_one_of_the_alternatives(): void
    {
        $group = ElasticGroup::bool('any')->minimumShouldMatch(1)->children([
            ElasticFilter::exists('deleted_by')->alias('has_del')->inShould(),
            ElasticFilter::term('status')->alias('st')->inShould(),
        ]);

        $query = $group->buildGroupQuery(['has_del' => false, 'st' => 'a']);

        $this->assertSame([
            'bool' => [
                'should' => [
                    ['bool' => ['must_not' => [['exists' => ['field' => 'deleted_by']]]]],
                    ['term' => ['status' => ['value' => 'a']]],
                ],
                'minimum_should_match' => 1,
            ],
        ], $query?->toArray());
    }
}
