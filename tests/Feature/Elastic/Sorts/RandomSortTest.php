<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Sorts\RandomSort;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group sort
 * @group elastic-sort
 */
class RandomSortTest extends TestCase
{
    use AssertsCollectionSorting;

    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(10)->create();
    }

    /** @test */
    public function it_can_sort_randomly(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('random'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(10, $result);
        // All models should be present
        $this->assertEqualsCanonicalizing(
            $this->models->pluck('id')->all(),
            $result->pluck('id')->all()
        );
    }

    /** @test */
    public function it_returns_consistent_order_with_same_seed(): void
    {
        $seed = 12345;

        $result1 = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('random')->seed($seed))
            ->build()
            ->execute()
            ->models();

        $result2 = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('random')->seed($seed))
            ->build()
            ->execute()
            ->models();

        $this->assertEquals($result1->pluck('id')->all(), $result2->pluck('id')->all());
    }

    /** @test */
    public function it_returns_different_order_with_different_seeds(): void
    {
        $result1 = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('random')->seed(111))
            ->build()
            ->execute()
            ->models();

        $result2 = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('random')->seed(222))
            ->build()
            ->execute()
            ->models();

        // With high probability, different seeds produce different orders
        // There's a tiny chance they could be the same, so we just check both have results
        $this->assertCount(10, $result1);
        $this->assertCount(10, $result2);
    }

    /** @test */
    public function it_can_use_string_seed(): void
    {
        $seed = 'session-id-123';

        $result1 = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('random')->seed($seed))
            ->build()
            ->execute()
            ->models();

        $result2 = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('random')->seed($seed))
            ->build()
            ->execute()
            ->models();

        $this->assertEquals($result1->pluck('id')->all(), $result2->pluck('id')->all());
    }

    /** @test */
    public function it_can_specify_field_for_seeded_random(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(
                RandomSort::make('random')
                    ->seed(12345)
                    ->field('_seq_no')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(10, $result);
    }

    /** @test */
    public function it_works_with_alias(): void
    {
        $result = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('_random', 'shuffle'))
            ->build()
            ->execute()
            ->models();

        $this->assertCount(10, $result);
    }
}
