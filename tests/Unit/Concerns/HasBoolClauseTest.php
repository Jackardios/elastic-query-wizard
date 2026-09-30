<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Concerns;

use Jackardios\ElasticQueryWizard\Concerns\HasBoolClause;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('concerns')]
class HasBoolClauseTest extends TestCase
{
    #[Test]
    public function it_defaults_to_filter_clause(): void
    {
        $object = $this->createObjectWithTrait();

        $this->assertNull($object->getClause());
        $this->assertEquals(BoolClause::Filter, $object->getEffectiveClause());
    }

    #[Test]
    public function in_filter_sets_filter_clause(): void
    {
        $object = $this->createObjectWithTrait();

        $result = $object->inFilter();

        $this->assertSame($object, $result);
        $this->assertEquals(BoolClause::Filter, $object->getClause());
        $this->assertEquals(BoolClause::Filter, $object->getEffectiveClause());
    }

    #[Test]
    public function in_must_sets_must_clause(): void
    {
        $object = $this->createObjectWithTrait();

        $result = $object->inMust();

        $this->assertSame($object, $result);
        $this->assertEquals(BoolClause::Must, $object->getClause());
        $this->assertEquals(BoolClause::Must, $object->getEffectiveClause());
    }

    #[Test]
    public function in_should_sets_should_clause(): void
    {
        $object = $this->createObjectWithTrait();

        $result = $object->inShould();

        $this->assertSame($object, $result);
        $this->assertEquals(BoolClause::Should, $object->getClause());
        $this->assertEquals(BoolClause::Should, $object->getEffectiveClause());
    }

    #[Test]
    public function in_must_not_sets_must_not_clause(): void
    {
        $object = $this->createObjectWithTrait();

        $result = $object->inMustNot();

        $this->assertSame($object, $result);
        $this->assertEquals(BoolClause::MustNot, $object->getClause());
        $this->assertEquals(BoolClause::MustNot, $object->getEffectiveClause());
    }

    #[Test]
    public function get_effective_clause_returns_explicit_clause_over_default(): void
    {
        $object = $this->createObjectWithCustomDefault(BoolClause::Must);

        // Without explicit clause, returns custom default
        $this->assertEquals(BoolClause::Must, $object->getEffectiveClause());

        // With explicit clause, returns explicit value
        $object->inShould();
        $this->assertEquals(BoolClause::Should, $object->getEffectiveClause());
    }

    private function createObjectWithTrait(): object
    {
        return new class
        {
            use HasBoolClause;
        };
    }

    private function createObjectWithCustomDefault(BoolClause $default): object
    {
        return new class($default)
        {
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
