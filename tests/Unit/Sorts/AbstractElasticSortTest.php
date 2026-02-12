<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Sorts;

use Jackardios\ElasticQueryWizard\Sorts\AbstractElasticSort;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 * @group sort
 */
class AbstractElasticSortTest extends TestCase
{
    /** @test */
    public function apply_returns_subject_unchanged_for_non_search_builder(): void
    {
        $sort = $this->createSort();
        $subject = new \stdClass();

        $result = $sort->apply($subject, 'asc');

        $this->assertSame($subject, $result);
    }

    /** @test */
    public function apply_calls_handle_when_subject_is_search_builder(): void
    {
        $sort = new class('property', 'alias') extends AbstractElasticSort {
            public bool $handleCalled = false;
            public ?string $capturedDirection = null;

            public function __construct(string $property, ?string $alias = null)
            {
                parent::__construct($property, $alias);
            }

            public static function make(string $property, ?string $alias = null): static
            {
                return new static($property, $alias);
            }

            public function getType(): string
            {
                return 'test';
            }

            public function handle(SearchBuilder $builder, string $direction): void
            {
                $this->handleCalled = true;
                $this->capturedDirection = $direction;
            }
        };

        $searchBuilder = $this->createMock(SearchBuilder::class);

        $result = $sort->apply($searchBuilder, 'desc');

        $this->assertSame($searchBuilder, $result);
        $this->assertTrue($sort->handleCalled);
        $this->assertEquals('desc', $sort->capturedDirection);
    }

    /** @test */
    public function get_type_returns_correct_type(): void
    {
        $sort = $this->createSort();

        $this->assertEquals('test', $sort->getType());
    }

    /** @test */
    public function get_property_returns_property(): void
    {
        $sort = $this->createSort();

        $this->assertEquals('property', $sort->getProperty());
    }

    /** @test */
    public function get_name_returns_alias_when_set(): void
    {
        $sort = $this->createSort();

        $this->assertEquals('alias', $sort->getName());
    }

    private function createSort(): AbstractElasticSort
    {
        return new class('property', 'alias') extends AbstractElasticSort {
            public function __construct(string $property, ?string $alias = null)
            {
                parent::__construct($property, $alias);
            }

            public static function make(string $property, ?string $alias = null): static
            {
                return new static($property, $alias);
            }

            public function getType(): string
            {
                return 'test';
            }

            public function handle(SearchBuilder $builder, string $direction): void
            {
                // no-op for test
            }
        };
    }
}
