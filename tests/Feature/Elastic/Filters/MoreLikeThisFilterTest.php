<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Filters;

use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Filters\MoreLikeThisFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group filter
 * @group elastic-filter
 */
class MoreLikeThisFilterTest extends TestCase
{
    protected Collection $models;

    protected function setUp(): void
    {
        parent::setUp();

        // Create documents with similar content for MLT testing
        $this->models = collect([
            TestModel::factory()->create([
                'name' => 'JavaScript programming guide for beginners developers',
                'category' => 'programming',
            ]),
            TestModel::factory()->create([
                'name' => 'JavaScript advanced programming techniques for developers',
                'category' => 'programming',
            ]),
            TestModel::factory()->create([
                'name' => 'Python programming guide for beginners developers',
                'category' => 'programming',
            ]),
            TestModel::factory()->create([
                'name' => 'Cooking recipes for healthy meals',
                'category' => 'cooking',
            ]),
            TestModel::factory()->create([
                'name' => 'Travel destinations in Europe',
                'category' => 'travel',
            ]),
        ]);
    }

    /** @test */
    public function it_can_find_similar_documents_by_text(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'similar' => 'JavaScript programming guide developers',
            ])
            ->allowedFilters(
                MoreLikeThisFilter::make(['name'], 'similar')
                    ->minTermFreq(1)
                    ->minDocFreq(1)
            )
            ->build()
            ->execute()
            ->models();

        // Should find JavaScript and potentially Python programming documents
        $this->assertGreaterThanOrEqual(1, $result->count());
        $this->assertTrue(
            $result->contains('id', $this->models[0]->id)
            || $result->contains('id', $this->models[1]->id)
        );
    }

    /** @test */
    public function it_can_find_similar_documents_with_min_term_freq(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'similar' => 'programming developers guide beginners',
            ])
            ->allowedFilters(
                MoreLikeThisFilter::make(['name'], 'similar')
                    ->minTermFreq(1)
                    ->minDocFreq(1)
            )
            ->build()
            ->execute()
            ->models();

        // Should find programming-related documents
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /** @test */
    public function it_returns_no_results_for_unrelated_text(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'similar' => 'quantum physics black holes universe cosmology astronomy',
            ])
            ->allowedFilters(
                MoreLikeThisFilter::make(['name'], 'similar')
                    ->minTermFreq(1)
                    ->minDocFreq(1)
            )
            ->build()
            ->execute()
            ->models();

        // Should not find any similar documents
        $this->assertCount(0, $result);
    }

    /** @test */
    public function it_allows_empty_filter_value(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'similar' => '',
            ])
            ->allowedFilters(
                MoreLikeThisFilter::make(['name'], 'similar')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertCount(5, $result);
    }

    /** @test */
    public function it_can_search_multiple_fields(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'similar' => 'programming developers',
            ])
            ->allowedFilters(
                MoreLikeThisFilter::make(['name', 'category'], 'similar')
                    ->minTermFreq(1)
                    ->minDocFreq(1)
            )
            ->build()
            ->execute()
            ->models();

        // Should find programming-related documents
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /** @test */
    public function it_can_use_min_doc_freq(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'similar' => 'programming developers',
            ])
            ->allowedFilters(
                MoreLikeThisFilter::make(['name'], 'similar')
                    ->minTermFreq(1)
                    ->minDocFreq(2) // Term must appear in at least 2 docs
            )
            ->build()
            ->execute()
            ->models();

        // Should still find programming documents
        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /** @test */
    public function it_can_limit_query_terms(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'similar' => 'JavaScript programming guide for beginners developers advanced techniques',
            ])
            ->allowedFilters(
                MoreLikeThisFilter::make(['name'], 'similar')
                    ->minTermFreq(1)
                    ->minDocFreq(1)
                    ->maxQueryTerms(5)
            )
            ->build()
            ->execute()
            ->models();

        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /** @test */
    public function it_can_use_alias(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'like' => 'programming developers',
            ])
            ->allowedFilters(
                MoreLikeThisFilter::make(['name'], 'similar', 'like')
                    ->minTermFreq(1)
                    ->minDocFreq(1)
            )
            ->build()
            ->execute()
            ->models();

        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /** @test */
    public function it_can_set_minimum_should_match(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'similar' => 'programming developers',
            ])
            ->allowedFilters(
                MoreLikeThisFilter::make(['name'], 'similar')
                    ->minTermFreq(1)
                    ->minDocFreq(1)
                    ->minimumShouldMatch('30%')
            )
            ->build()
            ->execute()
            ->models();

        $this->assertGreaterThanOrEqual(1, $result->count());
    }

    /** @test */
    public function it_can_filter_by_word_length(): void
    {
        $result = $this
            ->createElasticWizardWithFilters([
                'similar' => 'programming developers guide',
            ])
            ->allowedFilters(
                MoreLikeThisFilter::make(['name'], 'similar')
                    ->minTermFreq(1)
                    ->minDocFreq(1)
                    ->minWordLength(5) // Skip short words
            )
            ->build()
            ->execute()
            ->models();

        $this->assertGreaterThanOrEqual(1, $result->count());
    }
}
