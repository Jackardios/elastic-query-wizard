<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests for handling 0/"0" values in filters.
 */
#[Group('unit')]
#[Group('filter')]
class ZeroValueTest extends UnitTestCase
{
    #[Test]
    public function fuzzy_filter_handles_zero_integer_value(): void
    {
        $filter = ElasticFilter::fuzzy('field');
        $query = $filter->buildQuery(0);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('fuzzy', $array);
        $this->assertArrayHasKey('field', $array['fuzzy']);
        $this->assertEquals('0', $array['fuzzy']['field']['value']);
    }

    #[Test]
    public function fuzzy_filter_handles_zero_string_value(): void
    {
        $filter = ElasticFilter::fuzzy('field');
        $query = $filter->buildQuery('0');

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('fuzzy', $array);
        $this->assertArrayHasKey('field', $array['fuzzy']);
        $this->assertEquals('0', $array['fuzzy']['field']['value']);
    }

    #[Test]
    public function prefix_filter_handles_zero_integer_value(): void
    {
        $filter = ElasticFilter::prefix('field');
        $query = $filter->buildQuery(0);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('prefix', $array);
        $this->assertArrayHasKey('field', $array['prefix']);
        $this->assertEquals('0', $array['prefix']['field']['value']);
    }

    #[Test]
    public function prefix_filter_handles_zero_string_value(): void
    {
        $filter = ElasticFilter::prefix('field');
        $query = $filter->buildQuery('0');

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('prefix', $array);
        $this->assertArrayHasKey('field', $array['prefix']);
        $this->assertEquals('0', $array['prefix']['field']['value']);
    }

    #[Test]
    public function regexp_filter_handles_zero_integer_value(): void
    {
        $filter = ElasticFilter::regexp('field');
        $query = $filter->buildQuery(0);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('regexp', $array);
        $this->assertArrayHasKey('field', $array['regexp']);
        $this->assertEquals('0', $array['regexp']['field']['value']);
    }

    #[Test]
    public function regexp_filter_handles_zero_string_value(): void
    {
        $filter = ElasticFilter::regexp('field');
        $query = $filter->buildQuery('0');

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('regexp', $array);
        $this->assertArrayHasKey('field', $array['regexp']);
        $this->assertEquals('0', $array['regexp']['field']['value']);
    }

    #[Test]
    public function wildcard_filter_handles_zero_integer_value(): void
    {
        $filter = ElasticFilter::wildcard('field');
        $query = $filter->buildQuery(0);

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('wildcard', $array);
        $this->assertArrayHasKey('field', $array['wildcard']);
        $this->assertEquals('0', $array['wildcard']['field']['value']);
    }

    #[Test]
    public function wildcard_filter_handles_zero_string_value(): void
    {
        $filter = ElasticFilter::wildcard('field');
        $query = $filter->buildQuery('0');

        $this->assertNotNull($query);
        $array = $query->toArray();

        $this->assertArrayHasKey('wildcard', $array);
        $this->assertArrayHasKey('field', $array['wildcard']);
        $this->assertEquals('0', $array['wildcard']['field']['value']);
    }

    #[Test]
    public function fuzzy_filter_handles_empty_array(): void
    {
        $filter = ElasticFilter::fuzzy('field');
        $query = $filter->buildQuery([]);

        $this->assertNull($query);
    }

    #[Test]
    public function prefix_filter_handles_empty_array(): void
    {
        $filter = ElasticFilter::prefix('field');
        $query = $filter->buildQuery([]);

        $this->assertNull($query);
    }

    #[Test]
    public function regexp_filter_handles_empty_array(): void
    {
        $filter = ElasticFilter::regexp('field');
        $query = $filter->buildQuery([]);

        $this->assertNull($query);
    }

    #[Test]
    public function wildcard_filter_handles_empty_array(): void
    {
        $filter = ElasticFilter::wildcard('field');
        $query = $filter->buildQuery([]);

        $this->assertNull($query);
    }
}
