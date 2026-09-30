<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\EsScoutDriver\Query\QueryInterface;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('filter')]
class AbstractElasticFilterTest extends TestCase
{
    #[Test]
    public function apply_returns_subject_unchanged_for_non_search_builder(): void
    {
        $filter = $this->createFilter();
        $subject = new \stdClass;

        $result = $filter->apply($subject, 'value');

        $this->assertSame($subject, $result);
    }

    #[Test]
    public function apply_calls_handle_when_subject_is_search_builder(): void
    {
        $handleCalled = false;
        $capturedValue = null;

        $filter = new class('property', 'alias') extends AbstractElasticFilter
        {
            public bool $handleCalled = false;

            public mixed $capturedValue = null;

            public function __construct(string $property, ?string $alias = null)
            {
                parent::__construct($property, $alias);
            }

            public static function make(string $property, ?string $alias = null): static
            {
                return new self($property, $alias);
            }

            public function buildQuery(mixed $value): ?QueryInterface
            {
                return null;
            }

            public function handle(SearchBuilder $builder, mixed $value): void
            {
                $this->handleCalled = true;
                $this->capturedValue = $value;
            }
        };

        $searchBuilder = $this->createStub(SearchBuilder::class);

        $result = $filter->apply($searchBuilder, 'test_value');

        $this->assertSame($searchBuilder, $result);
        $this->assertTrue($filter->handleCalled);
        $this->assertEquals('test_value', $filter->capturedValue);
    }

    #[Test]
    public function get_property_returns_property(): void
    {
        $filter = $this->createFilter();

        $this->assertEquals('property', $filter->getProperty());
    }

    #[Test]
    public function get_name_returns_alias_when_set(): void
    {
        $filter = $this->createFilter();

        $this->assertEquals('alias', $filter->getName());
    }

    #[Test]
    public function get_name_returns_property_when_no_alias(): void
    {
        $filter = new class('property') extends AbstractElasticFilter
        {
            public function __construct(string $property, ?string $alias = null)
            {
                parent::__construct($property, $alias);
            }

            public static function make(string $property, ?string $alias = null): static
            {
                return new self($property, $alias);
            }

            public function buildQuery(mixed $value): ?QueryInterface
            {
                return null;
            }

            public function handle(SearchBuilder $builder, mixed $value): void
            {
                // no-op for test
            }
        };

        $this->assertEquals('property', $filter->getName());
    }

    private function createFilter(): AbstractElasticFilter
    {
        return new class('property', 'alias') extends AbstractElasticFilter
        {
            public function __construct(string $property, ?string $alias = null)
            {
                parent::__construct($property, $alias);
            }

            public static function make(string $property, ?string $alias = null): static
            {
                return new self($property, $alias);
            }

            public function buildQuery(mixed $value): ?QueryInterface
            {
                return null;
            }

            public function handle(SearchBuilder $builder, mixed $value): void
            {
                // no-op for test
            }
        };
    }
}
