<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Sorts;

use Jackardios\ElasticQueryWizard\Sorts\RandomSort;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use stdClass;

/**
 * @group unit
 * @group sort
 */
class RandomSortQueryTest extends UnitTestCase
{
    /** @test */
    public function it_adds_random_score_function(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('shuffle'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'function_score' => [
                'functions' => [
                    ['random_score' => new stdClass()],
                ],
                'boost_mode' => 'replace',
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_sorts_by_score_ascending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('shuffle'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            ['_score' => 'asc'],
        ], $sorts);
    }

    /** @test */
    public function it_sorts_by_score_descending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('-shuffle')
            ->allowedSorts(RandomSort::make('shuffle'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            ['_score' => 'desc'],
        ], $sorts);
    }

    /** @test */
    public function it_applies_seed(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('shuffle')->seed(12345));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'function_score' => [
                'functions' => [
                    [
                        'random_score' => [
                            'seed' => 12345,
                            'field' => '_seq_no',
                        ],
                    ],
                ],
                'boost_mode' => 'replace',
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_applies_string_seed(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('shuffle')->seed('session_abc123'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'function_score' => [
                'functions' => [
                    [
                        'random_score' => [
                            'seed' => 'session_abc123',
                            'field' => '_seq_no',
                        ],
                    ],
                ],
                'boost_mode' => 'replace',
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_uses_default_seq_no_field_with_seed(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('shuffle')->seed(42));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('function_score', $queries[0]);
        $this->assertEquals('_seq_no', $queries[0]['function_score']['functions'][0]['random_score']['field']);
    }

    /** @test */
    public function it_applies_custom_field_with_seed(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(
                RandomSort::make('shuffle')
                    ->seed(12345)
                    ->field('_id')
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'function_score' => [
                'functions' => [
                    [
                        'random_score' => [
                            'seed' => 12345,
                            'field' => '_id',
                        ],
                    ],
                ],
                'boost_mode' => 'replace',
            ],
        ], $queries[0]);
    }

    /** @test */
    public function it_uses_alias_correctly(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('shuffle', 'random'));
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('function_score', $queries[0]);
    }

    /** @test */
    public function it_returns_correct_type(): void
    {
        $sort = RandomSort::make('shuffle');

        $this->assertEquals('random', $sort->getType());
    }

    /** @test */
    public function it_uses_default_property_name(): void
    {
        $sort = RandomSort::make();

        $this->assertEquals('_random', $sort->getProperty());
        $this->assertEquals('_random', $sort->getName());
    }

    /** @test */
    public function it_combines_seed_and_field(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(
                RandomSort::make('shuffle')
                    ->seed(99999)
                    ->field('user_id')
            );
        $wizard->build();

        $queries = $this->getMustQueries($wizard->boolQuery());
        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'function_score' => [
                'functions' => [
                    [
                        'random_score' => [
                            'seed' => 99999,
                            'field' => 'user_id',
                        ],
                    ],
                ],
                'boost_mode' => 'replace',
            ],
        ], $queries[0]);

        $this->assertEquals([
            ['_score' => 'asc'],
        ], $sorts);
    }
}
