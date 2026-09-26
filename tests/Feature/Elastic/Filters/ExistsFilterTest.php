<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Filters\ExistsFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('elastic')]
#[Group('filter')]
#[Group('elastic-filter')]
class ExistsFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(5)->create();
    }

    #[Test]
    public function it_can_filter_with_truthy_value(): void
    {
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => '1',
            ])
            ->allowedFilters(ExistsFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    #[Test]
    public function it_can_filter_with_falsy_value(): void
    {
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => '0',
            ])
            ->allowedFilters(ExistsFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(0, $modelsResult);
    }

    #[Test]
    public function it_allows_empty_filter_value(): void
    {
        $modelsResult = $this
            ->createElasticWizardWithFilters([
                'category' => '',
            ])
            ->allowedFilters(ExistsFilter::make('category'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $modelsResult);
    }

    #[Test]
    public function a_missing_field_is_one_of_the_alternatives_of_an_or_group(): void
    {
        $expected = $this->createNamedModelsWithAndWithoutCategory();

        $ids = $this
            ->createElasticWizardWithFilters(['has_category' => 'false', 'name' => 'a'])
            ->allowedFilters(ElasticGroup::bool('any')->minimumShouldMatch(1)->children([
                ElasticFilter::exists('category')->alias('has_category')->inShould(),
                ElasticFilter::term('name.keyword')->alias('name')->inShould(),
            ]))
            ->build()
            ->execute()
            ->models()
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$expected['a-x'], $expected['a-'], $expected['b-']], $ids);
    }

    #[Test]
    public function a_falsy_value_in_must_not_keeps_the_models_that_have_the_field(): void
    {
        $expected = $this->createNamedModelsWithAndWithoutCategory();

        $ids = $this
            ->createElasticWizardWithFilters(['category' => 'false'])
            ->allowedFilters(ExistsFilter::make('category')->inMustNot())
            ->build()
            ->execute()
            ->models()
            ->pluck('id')
            ->sort()
            ->values()
            ->all();

        $this->assertSame([$expected['a-x'], $expected['b-x']], array_values(array_intersect($ids, $expected)));
        $this->assertNotContains($expected['a-'], $ids);
        $this->assertNotContains($expected['b-'], $ids);
    }

    /**
     * @return array{'a-x': int, 'a-': int, 'b-x': int, 'b-': int}
     */
    private function createNamedModelsWithAndWithoutCategory(): array
    {
        return [
            'a-x' => TestModel::factory()->create(['name' => 'a', 'category' => 'x'])->id,
            'a-' => TestModel::factory()->create(['name' => 'a', 'category' => null])->id,
            'b-x' => TestModel::factory()->create(['name' => 'b', 'category' => 'x'])->id,
            'b-' => TestModel::factory()->create(['name' => 'b', 'category' => null])->id,
        ];
    }
}
