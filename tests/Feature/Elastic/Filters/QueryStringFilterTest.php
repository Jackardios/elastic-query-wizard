<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\QueryStringFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('elastic')]
#[Group('filter')]
#[Group('elastic-filter')]
class QueryStringFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'John Smith Developer', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'Jane Doe Designer', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'Bob Johnson Developer', 'category' => 'clothing']),
            TestModel::factory()->create(['name' => 'Alice Williams Manager', 'category' => 'electronics']),
        ]);
    }

    #[Test]
    public function it_can_filter_with_simple_query(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['q' => 'Developer'])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[0]->id, $this->models[2]->id],
            $result->pluck('id')->all()
        );
    }

    #[Test]
    public function it_can_filter_with_and_operator(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['q' => 'John AND Developer'])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $result);
        $this->assertEquals($this->models[0]->id, $result->first()->id);
    }

    #[Test]
    public function it_can_filter_with_or_operator(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['q' => 'Designer OR Manager'])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing(
            [$this->models[1]->id, $this->models[3]->id],
            $result->pluck('id')->all()
        );
    }

    #[Test]
    public function it_returns_no_results_for_non_matching_query(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['q' => 'nonexistent'])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $result);
    }

    #[Test]
    public function it_allows_empty_filter_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['q' => ''])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(4, $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function acceptedQueries(): array
    {
        return [
            'star inside a regular expression' => ['/a *b/'],
            'slash inside a term' => ['a/*b/'],
            'plus inside a term' => ['a+*b'],
            'operator characters inside a term' => ['a&&*b'],
            'escaped space' => ['a\\ *b'],
            'escaped backslash inside a term' => ['\\\\*a'],
            'escaped star' => ['\\*a'],
            'lone star with fuzziness' => ['*~'],
            'lone star with a boost' => ['*^2'],
            'open range' => ['{a TO *}'],
            'star in a field phrase' => ['name:"*b"'],
            'field exists' => ['name:*'],
            'phrase' => ['"a *b"'],
            'inner and trailing wildcards' => ['a?c ab*'],
        ];
    }

    #[Test]
    #[DataProvider('acceptedQueries')]
    public function a_query_the_filter_lets_through_is_accepted_by_elasticsearch(string $query): void
    {
        $this
            ->createElasticWizardWithFilters(['q' => $query])
            ->allowedFilters(QueryStringFilter::make('name', 'q'))
            ->build()
            ->execute();

        $this->addToAssertionCount(1);
    }
}
