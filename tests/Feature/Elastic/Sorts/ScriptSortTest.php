<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Sorts\ScriptSort;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group sort
 * @group elastic-sort
 */
class ScriptSortTest extends TestCase
{
    use AssertsCollectionSorting;

    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = collect([
            TestModel::factory()->create(['name' => 'Alice', 'category' => 'books']),
            TestModel::factory()->create(['name' => 'Bob', 'category' => 'electronics']),
            TestModel::factory()->create(['name' => 'Charlie', 'category' => 'clothing']),
        ]);
    }

    /** @test */
    public function it_can_sort_by_script_ascending(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('name_length')
            ->allowedSorts(
                ScriptSort::make("doc['name.keyword'].value.length()", 'name_length')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Bob (3), Alice (5), Charlie (7)
        $this->assertEquals([$this->models[1]->id, $this->models[0]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_by_script_descending(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('-name_length')
            ->allowedSorts(
                ScriptSort::make("doc['name.keyword'].value.length()", 'name_length')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Charlie (7), Alice (5), Bob (3)
        $this->assertEquals([$this->models[2]->id, $this->models[0]->id, $this->models[1]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_use_script_with_params(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('custom')
            ->allowedSorts(
                ScriptSort::make("doc['name.keyword'].value.length() * params.multiplier", 'custom')
                    ->params(['multiplier' => 2])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Sort order should be same as without params (just scaled)
        $this->assertEquals([$this->models[1]->id, $this->models[0]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_sort_by_category_priority(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('priority')
            ->allowedSorts(
                ScriptSort::make(
                    "params.priorities.containsKey(doc['category'].value) ? params.priorities[doc['category'].value] : 999",
                    'priority'
                )->params([
                    'priorities' => [
                        'electronics' => 1,
                        'books' => 2,
                        'clothing' => 3,
                    ],
                ])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // electronics (1), books (2), clothing (3)
        $this->assertEquals([$this->models[1]->id, $this->models[0]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_use_string_type(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('first_letter')
            ->allowedSorts(
                ScriptSort::make("doc['name.keyword'].value.substring(0, 1)", 'first_letter')
                    ->type('string')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Alice (A), Bob (B), Charlie (C)
        $this->assertEquals([$this->models[0]->id, $this->models[1]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_use_alias(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('len')
            ->allowedSorts(
                ScriptSort::make("doc['name.keyword'].value.length()", 'name_length', 'len')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        $this->assertEquals([$this->models[1]->id, $this->models[0]->id, $this->models[2]->id], $result->pluck('id')->all());
    }

    /** @test */
    public function it_can_calculate_composite_score(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('-composite')
            ->allowedSorts(
                ScriptSort::make("doc['id'].value * params.weight", 'composite')
                    ->params(['weight' => 10])
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $result);
        // Higher IDs first (descending)
        $this->assertEquals(
            $this->models->sortByDesc('id')->pluck('id')->all(),
            $result->pluck('id')->all()
        );
    }
}
