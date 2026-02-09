<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Sorts;

use Jackardios\ElasticQueryWizard\ElasticSort;
use Jackardios\ElasticQueryWizard\Sorts\ScoreSort;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;

/**
 * @group unit
 * @group sort
 */
class ScoreSortQueryTest extends UnitTestCase
{
    /** @test */
    public function it_builds_a_score_sort_ascending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('score')
            ->allowedSorts(ScoreSort::make('score'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals(['_score' => 'asc'], $sorts[0]);
    }

    /** @test */
    public function it_builds_a_score_sort_descending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('-score')
            ->allowedSorts(ScoreSort::make('score'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals(['_score' => 'desc'], $sorts[0]);
    }

    /** @test */
    public function it_works_with_factory_method(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('-relevance')
            ->allowedSorts(ElasticSort::score('relevance'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $sorts);
        $this->assertEquals(['_score' => 'desc'], $sorts[0]);
    }

    /** @test */
    public function it_defaults_to_score_as_property_name(): void
    {
        $sort = ScoreSort::make();

        $this->assertEquals('_score', $sort->getProperty());
    }
}
