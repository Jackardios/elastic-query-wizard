<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Sorts\RandomSort;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\EsScoutDriver\Search\Hit;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('elastic')]
#[Group('sort')]
#[Group('elastic-sort')]
class RandomSortTest extends TestCase
{
    use AssertsCollectionSorting;

    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        $this->models = TestModel::factory()->count(10)->create();
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_returns_different_order_with_different_seeds(): void
    {
        $builder1 = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('random')->seed(111))
            ->build();
        $builder2 = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('random')->seed(222))
            ->build();

        $this->assertSame(
            ['seed' => 111, 'field' => '_seq_no'],
            $builder1->getQuery()['function_score']['functions'][0]['random_score'] ?? null
        );
        $this->assertSame(
            ['seed' => 222, 'field' => '_seq_no'],
            $builder2->getQuery()['function_score']['functions'][0]['random_score'] ?? null
        );

        $ids1 = $builder1->execute()->models()->pluck('id')->all();
        $ids2 = $builder2->execute()->models()->pluck('id')->all();

        $this->assertEqualsCanonicalizing($this->models->pluck('id')->all(), $ids1);
        $this->assertEqualsCanonicalizing($this->models->pluck('id')->all(), $ids2);
        $this->assertNotSame($ids1, $ids2);
    }

    #[Test]
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

    #[Test]
    public function it_can_specify_field_for_seeded_random(): void
    {
        $builder = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(
                RandomSort::make('random')
                    ->seed(12345)
                    ->field('id')
            )
            ->build();

        $this->assertSame(
            ['seed' => 12345, 'field' => 'id'],
            $builder->getQuery()['function_score']['functions'][0]['random_score'] ?? null
        );
        $this->assertEqualsCanonicalizing(
            $this->models->pluck('id')->all(),
            $builder->execute()->models()->pluck('id')->all()
        );
    }

    #[Test]
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

    #[Test]
    public function the_relevance_of_scoring_filters_does_not_change_the_random_order(): void
    {
        TestModel::factory()->create(['name' => 'shoes']);
        TestModel::factory()->create(['name' => 'shoes shoes shoes']);
        TestModel::factory()->create(['name' => 'red shoes for running in the rain']);

        $scores = fn (array $query): array => $this
            ->createElasticWizardFromQuery($query)
            ->allowedFilters(ElasticFilter::match('name', 'q'))
            ->allowedSorts(RandomSort::make('random')->seed(42))
            ->build()
            ->size(50)
            ->execute()
            ->hits()
            ->mapWithKeys(fn (Hit $hit) => [$hit->documentId => $hit->score])
            ->all();

        $filtered = $scores(['filter' => ['q' => 'shoes'], 'sort' => 'random']);
        $unfiltered = $scores(['sort' => 'random']);

        $this->assertCount(3, $filtered);
        $this->assertCount(13, $unfiltered);
        $this->assertEquals(array_intersect_key($unfiltered, $filtered), $filtered);
    }
}
