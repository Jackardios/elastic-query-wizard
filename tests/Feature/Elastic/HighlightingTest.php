<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @group elastic
 * @group highlighting
 * @group elastic-highlighting
 */
class HighlightingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        TestModel::factory()->create(['name' => 'John Smith Developer', 'category' => 'programming']);
        TestModel::factory()->create(['name' => 'Jane Doe Designer', 'category' => 'design']);
        TestModel::factory()->create(['name' => 'Bob Johnson Manager', 'category' => 'management']);
    }

    /** @test */
    public function it_can_add_highlighting_to_search(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Developer'])
            ->allowedFilters(MatchFilter::make('name'))
            ->tapSearchBuilder(function ($builder) {
                $builder->highlight('name');
            })
            ->build()
            ->execute();

        $this->assertCount(1, $result->models());

        $hits = $result->hits();
        $this->assertNotEmpty($hits);
    }

    /** @test */
    public function it_can_highlight_multiple_fields(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'John'])
            ->allowedFilters(MatchFilter::make('name'))
            ->tapSearchBuilder(function ($builder) {
                $builder->highlight('name');
                $builder->highlight('category');
            })
            ->build()
            ->execute();

        $this->assertCount(1, $result->models());
    }

    /** @test */
    public function it_can_use_custom_highlight_tags(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Developer'])
            ->allowedFilters(MatchFilter::make('name'))
            ->tapSearchBuilder(function ($builder) {
                $builder->highlight('name', [
                    'pre_tags' => ['<mark>'],
                    'post_tags' => ['</mark>'],
                ]);
            })
            ->build()
            ->execute();

        $this->assertCount(1, $result->models());
    }

    /** @test */
    public function it_can_highlight_with_fragment_size(): void
    {
        TestModel::factory()->create([
            'name' => 'This is a very long name with Developer keyword somewhere in the middle of the text for testing purposes',
        ]);

        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Developer'])
            ->allowedFilters(MatchFilter::make('name'))
            ->tapSearchBuilder(function ($builder) {
                $builder->highlight('name', [
                    'fragment_size' => 50,
                    'number_of_fragments' => 3,
                ]);
            })
            ->build()
            ->execute();

        $this->assertGreaterThanOrEqual(1, $result->models()->count());
    }

    /** @test */
    public function it_returns_results_without_highlighting_when_not_configured(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Developer'])
            ->allowedFilters(MatchFilter::make('name'))
            ->build()
            ->execute();

        $this->assertCount(1, $result->models());
    }

    /** @test */
    public function it_can_highlight_with_require_field_match(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Developer'])
            ->allowedFilters(MatchFilter::make('name'))
            ->tapSearchBuilder(function ($builder) {
                $builder->highlight('name', [
                    'require_field_match' => true,
                ]);
            })
            ->build()
            ->execute();

        $this->assertCount(1, $result->models());
    }

    /** @test */
    public function it_can_use_fvh_highlighter(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Developer'])
            ->allowedFilters(MatchFilter::make('name'))
            ->tapSearchBuilder(function ($builder) {
                $builder->highlight('name', [
                    'type' => 'plain',
                ]);
            })
            ->build()
            ->execute();

        $this->assertCount(1, $result->models());
    }

    /** @test */
    public function it_can_highlight_with_boundary_settings(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Developer'])
            ->allowedFilters(MatchFilter::make('name'))
            ->tapSearchBuilder(function ($builder) {
                $builder->highlight('name', [
                    'boundary_scanner' => 'sentence',
                    'boundary_max_scan' => 20,
                ]);
            })
            ->build()
            ->execute();

        $this->assertCount(1, $result->models());
    }

    /** @test */
    public function it_can_access_highlight_data_from_hits(): void
    {
        $result = $this
            ->createElasticWizardWithFilters(['name' => 'Developer'])
            ->allowedFilters(MatchFilter::make('name'))
            ->tapSearchBuilder(function ($builder) {
                $builder->highlight('name');
            })
            ->build()
            ->execute();

        $hits = $result->hits();
        $this->assertNotEmpty($hits);

        // Each hit should be accessible
        $firstHit = $hits->first();
        $this->assertNotNull($firstHit);
    }

    /** @test */
    public function it_works_with_pagination_and_highlighting(): void
    {
        TestModel::factory()->count(10)->create(['name' => 'Developer User']);

        $paginator = $this
            ->createElasticWizardWithFilters(['name' => 'Developer'])
            ->allowedFilters(MatchFilter::make('name'))
            ->tapSearchBuilder(function ($builder) {
                $builder->highlight('name');
            })
            ->build()
            ->paginate(5);

        $this->assertGreaterThanOrEqual(1, $paginator->total());
    }
}
