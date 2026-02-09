<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Concerns;

use BadMethodCallException;
use Jackardios\ElasticQueryWizard\Concerns\HasParameters;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 */
class HasParametersTest extends TestCase
{
    /** @test */
    public function it_applies_parameters_to_query_builder(): void
    {
        $trait = $this->createTraitUser();
        $trait->withParameters(['boost' => 1.5, 'fuzziness' => 'AUTO']);

        $mockBuilder = new class {
            public float $boost = 0;
            public string $fuzziness = '';

            public function boost(float $value): self
            {
                $this->boost = $value;
                return $this;
            }

            public function fuzziness(string $value): self
            {
                $this->fuzziness = $value;
                return $this;
            }
        };

        $result = $trait->applyParametersOnQuery($mockBuilder);

        $this->assertSame($mockBuilder, $result);
        $this->assertEquals(1.5, $mockBuilder->boost);
        $this->assertEquals('AUTO', $mockBuilder->fuzziness);
    }

    /** @test */
    public function it_throws_for_non_existent_method(): void
    {
        $trait = $this->createTraitUser();
        $trait->withParameters(['nonExistentMethod' => 'value']);

        $mockBuilder = new class {};

        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method "nonExistentMethod" does not exist');

        $trait->applyParametersOnQuery($mockBuilder);
    }

    /** @test */
    public function it_merges_parameters(): void
    {
        $trait = $this->createTraitUser();
        $trait->withParameters(['boost' => 1.5]);
        $trait->withParameters(['fuzziness' => 'AUTO']);

        $mockBuilder = new class {
            public float $boost = 0;
            public string $fuzziness = '';

            public function boost(float $value): self
            {
                $this->boost = $value;
                return $this;
            }

            public function fuzziness(string $value): self
            {
                $this->fuzziness = $value;
                return $this;
            }
        };

        $trait->applyParametersOnQuery($mockBuilder);

        $this->assertEquals(1.5, $mockBuilder->boost);
        $this->assertEquals('AUTO', $mockBuilder->fuzziness);
    }

    /** @test */
    public function it_converts_snake_case_to_camel_case(): void
    {
        $trait = $this->createTraitUser();
        $trait->withParameters(['max_expansions' => 50]);

        $mockBuilder = new class {
            public int $maxExpansions = 0;

            public function maxExpansions(int $value): self
            {
                $this->maxExpansions = $value;
                return $this;
            }
        };

        $trait->applyParametersOnQuery($mockBuilder);

        $this->assertEquals(50, $mockBuilder->maxExpansions);
    }

    /** @test */
    public function with_parameters_returns_self(): void
    {
        $trait = $this->createTraitUser();
        $result = $trait->withParameters(['boost' => 1.5]);

        $this->assertSame($trait, $result);
    }

    private function createTraitUser(): object
    {
        return new class {
            use HasParameters;
        };
    }
}
