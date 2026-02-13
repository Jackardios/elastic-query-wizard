<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Concerns;

use Jackardios\ElasticQueryWizard\Concerns\HasBoolClause;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 * @group concerns
 */
class HasBoolClauseTest extends TestCase
{
    /** @test */
    public function it_defaults_to_filter_clause(): void
    {
        $object = $this->createObjectWithTrait();

        $this->assertNull($object->getClause());
        $this->assertEquals(BoolClause::FILTER, $object->getEffectiveClause());
    }

    /** @test */
    public function in_filter_sets_filter_clause(): void
    {
        $object = $this->createObjectWithTrait();

        $result = $object->inFilter();

        $this->assertSame($object, $result);
        $this->assertEquals(BoolClause::FILTER, $object->getClause());
        $this->assertEquals(BoolClause::FILTER, $object->getEffectiveClause());
    }

    /** @test */
    public function in_must_sets_must_clause(): void
    {
        $object = $this->createObjectWithTrait();

        $result = $object->inMust();

        $this->assertSame($object, $result);
        $this->assertEquals(BoolClause::MUST, $object->getClause());
        $this->assertEquals(BoolClause::MUST, $object->getEffectiveClause());
    }

    /** @test */
    public function in_should_sets_should_clause(): void
    {
        $object = $this->createObjectWithTrait();

        $result = $object->inShould();

        $this->assertSame($object, $result);
        $this->assertEquals(BoolClause::SHOULD, $object->getClause());
        $this->assertEquals(BoolClause::SHOULD, $object->getEffectiveClause());
    }

    /** @test */
    public function in_must_not_sets_must_not_clause(): void
    {
        $object = $this->createObjectWithTrait();

        $result = $object->inMustNot();

        $this->assertSame($object, $result);
        $this->assertEquals(BoolClause::MUST_NOT, $object->getClause());
        $this->assertEquals(BoolClause::MUST_NOT, $object->getEffectiveClause());
    }

    /** @test */
    public function get_effective_clause_returns_explicit_clause_over_default(): void
    {
        $object = $this->createObjectWithCustomDefault(BoolClause::MUST);

        // Without explicit clause, returns custom default
        $this->assertEquals(BoolClause::MUST, $object->getEffectiveClause());

        // With explicit clause, returns explicit value
        $object->inShould();
        $this->assertEquals(BoolClause::SHOULD, $object->getEffectiveClause());
    }

    private function createObjectWithTrait(): object
    {
        return new class {
            use HasBoolClause;
        };
    }

    private function createObjectWithCustomDefault(BoolClause $default): object
    {
        return new class ($default) {
            use HasBoolClause;

            private BoolClause $customDefault;

            public function __construct(BoolClause $default)
            {
                $this->customDefault = $default;
            }

            protected function getDefaultClause(): BoolClause
            {
                return $this->customDefault;
            }
        };
    }
}
