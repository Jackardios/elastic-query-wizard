<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Includes;

use Illuminate\Database\Eloquent\Builder;
use Jackardios\ElasticQueryWizard\Includes\AbstractElasticInclude;
use Jackardios\EsScoutDriver\Search\SearchResult;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 * @group include
 */
class AbstractElasticIncludeTest extends TestCase
{
    /** @test */
    public function it_can_set_and_get_search_result(): void
    {
        $include = $this->createInclude();
        $searchResult = new SearchResult(['hits' => ['hits' => [], 'total' => ['value' => 0]]], fn() => collect());

        $result = $include->setSearchResult($searchResult);

        $this->assertSame($include, $result);
        $this->assertSame($searchResult, $include->getSearchResult());
    }

    /** @test */
    public function get_search_result_returns_null_by_default(): void
    {
        $include = $this->createInclude();

        $this->assertNull($include->getSearchResult());
    }

    /** @test */
    public function apply_returns_subject_unchanged_for_non_builder(): void
    {
        $include = $this->createInclude();
        $subject = new \stdClass();

        $result = $include->apply($subject);

        $this->assertSame($subject, $result);
    }

    /** @test */
    public function apply_calls_handle_eloquent_when_subject_is_builder(): void
    {
        $include = new class ('relation', 'alias') extends AbstractElasticInclude {
            public bool $handleCalled = false;

            public function __construct(string $relation, ?string $alias = null)
            {
                parent::__construct($relation, $alias);
            }

            public static function make(string $relation, ?string $alias = null): static
            {
                return new static($relation, $alias);
            }

            public function getType(): string
            {
                return 'test';
            }

            public function handleEloquent(Builder $eloquentBuilder): void
            {
                $this->handleCalled = true;
            }
        };

        $builder = $this->createMock(Builder::class);

        $result = $include->apply($builder);

        $this->assertSame($builder, $result);
        $this->assertTrue($include->handleCalled);
    }

    /** @test */
    public function get_type_returns_correct_type(): void
    {
        $include = $this->createInclude();

        $this->assertEquals('test', $include->getType());
    }

    private function createInclude(): AbstractElasticInclude
    {
        return new class ('relation', 'alias') extends AbstractElasticInclude {
            public function __construct(string $relation, ?string $alias = null)
            {
                parent::__construct($relation, $alias);
            }

            public static function make(string $relation, ?string $alias = null): static
            {
                return new static($relation, $alias);
            }

            public function getType(): string
            {
                return 'test';
            }

            public function handleEloquent(Builder $eloquentBuilder): void
            {
                // no-op for test
            }
        };
    }
}
