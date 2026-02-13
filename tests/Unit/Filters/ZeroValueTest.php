<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * Tests for handling 0/"0" values in filters.
 *
 * @group unit
 * @group filter
 */
class ZeroValueTest extends UnitTestCase
{
    /** @test */
    public function fuzzy_filter_handles_zero_integer_value(): void
    {
        $filter = ElasticFilter::fuzzy('field');
        $query = $filter->buildQuery([0]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('fuzzy', $array);
        $this->assertArrayHasKey('field', $array['fuzzy']);
        $this->assertEquals('0', $array['fuzzy']['field']['value']);
    }

    /** @test */
    public function fuzzy_filter_handles_zero_string_value(): void
    {
        $filter = ElasticFilter::fuzzy('field');
        $query = $filter->buildQuery(['0']);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('fuzzy', $array);
        $this->assertArrayHasKey('field', $array['fuzzy']);
        $this->assertEquals('0', $array['fuzzy']['field']['value']);
    }

    /** @test */
    public function prefix_filter_handles_zero_integer_value(): void
    {
        $filter = ElasticFilter::prefix('field');
        $query = $filter->buildQuery([0]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('prefix', $array);
        $this->assertArrayHasKey('field', $array['prefix']);
        $this->assertEquals('0', $array['prefix']['field']['value']);
    }

    /** @test */
    public function prefix_filter_handles_zero_string_value(): void
    {
        $filter = ElasticFilter::prefix('field');
        $query = $filter->buildQuery(['0']);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('prefix', $array);
        $this->assertArrayHasKey('field', $array['prefix']);
        $this->assertEquals('0', $array['prefix']['field']['value']);
    }

    /** @test */
    public function regexp_filter_handles_zero_integer_value(): void
    {
        $filter = ElasticFilter::regexp('field');
        $query = $filter->buildQuery([0]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('regexp', $array);
        $this->assertArrayHasKey('field', $array['regexp']);
        $this->assertEquals('0', $array['regexp']['field']['value']);
    }

    /** @test */
    public function regexp_filter_handles_zero_string_value(): void
    {
        $filter = ElasticFilter::regexp('field');
        $query = $filter->buildQuery(['0']);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('regexp', $array);
        $this->assertArrayHasKey('field', $array['regexp']);
        $this->assertEquals('0', $array['regexp']['field']['value']);
    }

    /** @test */
    public function wildcard_filter_handles_zero_integer_value(): void
    {
        $filter = ElasticFilter::wildcard('field');
        $query = $filter->buildQuery([0]);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('wildcard', $array);
        $this->assertArrayHasKey('field', $array['wildcard']);
        $this->assertEquals('0', $array['wildcard']['field']['value']);
    }

    /** @test */
    public function wildcard_filter_handles_zero_string_value(): void
    {
        $filter = ElasticFilter::wildcard('field');
        $query = $filter->buildQuery(['0']);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('wildcard', $array);
        $this->assertArrayHasKey('field', $array['wildcard']);
        $this->assertEquals('0', $array['wildcard']['field']['value']);
    }

    /** @test */
    public function fuzzy_filter_handles_empty_array(): void
    {
        $filter = ElasticFilter::fuzzy('field');
        $query = $filter->buildQuery([]);

        $this->assertNull($query);
    }

    /** @test */
    public function prefix_filter_handles_empty_array(): void
    {
        $filter = ElasticFilter::prefix('field');
        $query = $filter->buildQuery([]);

        $this->assertNull($query);
    }

    /** @test */
    public function regexp_filter_handles_empty_array(): void
    {
        $filter = ElasticFilter::regexp('field');
        $query = $filter->buildQuery([]);

        $this->assertNull($query);
    }

    /** @test */
    public function wildcard_filter_handles_empty_array(): void
    {
        $filter = ElasticFilter::wildcard('field');
        $query = $filter->buildQuery([]);

        $this->assertNull($query);
    }
}
