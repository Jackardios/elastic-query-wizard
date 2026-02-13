<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Enums;

use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 * @group enums
 */
class BoolClauseTest extends TestCase
{
    /** @test */
    public function it_has_filter_case(): void
    {
        $this->assertEquals('filter', BoolClause::FILTER->value);
    }

    /** @test */
    public function it_has_must_case(): void
    {
        $this->assertEquals('must', BoolClause::MUST->value);
    }

    /** @test */
    public function it_has_should_case(): void
    {
        $this->assertEquals('should', BoolClause::SHOULD->value);
    }

    /** @test */
    public function it_has_must_not_case(): void
    {
        $this->assertEquals('must_not', BoolClause::MUST_NOT->value);
    }

    /** @test */
    public function it_can_be_created_from_string(): void
    {
        $this->assertEquals(BoolClause::FILTER, BoolClause::from('filter'));
        $this->assertEquals(BoolClause::MUST, BoolClause::from('must'));
        $this->assertEquals(BoolClause::SHOULD, BoolClause::from('should'));
        $this->assertEquals(BoolClause::MUST_NOT, BoolClause::from('must_not'));
    }
}
