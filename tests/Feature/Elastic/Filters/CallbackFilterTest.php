<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Filters\CallbackFilter;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('elastic')]
#[Group('filter')]
#[Group('elastic-filter')]
class CallbackFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(3)->create();
    }

    #[Test]
    public function it_should_filter_by_closure(): void
    {
        $expectedName = 'Some New Testing Name '.Str::uuid()->toString();
        $expectedModel = TestModel::factory()->create(['name' => $expectedName]);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'callback' => $expectedModel->name,
            ])
            ->allowedFilters(
                CallbackFilter::make('callback', function (SearchBuilder $builder, mixed $value, string $property) {
                    $builder->must(Query::term('name.keyword', $value));
                })
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->name, $modelsResult->first()->name);
    }

    #[Test]
    public function it_should_filter_by_array_callback(): void
    {
        $expectedName = 'Some New Testing Name '.Str::uuid()->toString();
        $expectedModel = TestModel::factory()->create(['name' => $expectedName]);
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'callback' => $expectedModel->name,
            ])
            ->allowedFilters(CallbackFilter::make('callback', [$this, 'filterCallback']))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(1, $modelsResult);
        $this->assertEquals($expectedModel->name, $modelsResult->first()->name);
    }

    public function filterCallback(SearchBuilder $builder, mixed $value, string $property): void
    {
        $builder->must(Query::term('name.keyword', $value));
    }
}
