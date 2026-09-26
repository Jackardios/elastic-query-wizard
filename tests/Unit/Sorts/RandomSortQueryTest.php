<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Sorts;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\Sorts\RandomSort;
use Jackardios\ElasticQueryWizard\Sorts\ScoreSort;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use stdClass;

#[Group('unit')]
#[Group('sort')]
class RandomSortQueryTest extends UnitTestCase
{
    #[Test]
    public function it_adds_random_score_function(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('shuffle'));
        $wizard->build();

        $queries = [$wizard->getSubject()->getQuery()];

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'function_score' => [
                'functions' => [
                    ['random_score' => new stdClass],
                ],
                'boost_mode' => 'replace',
            ],
        ], $queries[0]);
    }

    #[Test]
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

    #[Test]
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

    #[Test]
    public function it_applies_seed(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('shuffle')->seed(12345));
        $wizard->build();

        $queries = [$wizard->getSubject()->getQuery()];

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

    #[Test]
    public function it_applies_string_seed(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('shuffle')->seed('session_abc123'));
        $wizard->build();

        $queries = [$wizard->getSubject()->getQuery()];

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

    #[Test]
    public function it_uses_default_seq_no_field_with_seed(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('shuffle')->seed(42));
        $wizard->build();

        $queries = [$wizard->getSubject()->getQuery()];

        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('function_score', $queries[0]);
        $this->assertEquals('_seq_no', $queries[0]['function_score']['functions'][0]['random_score']['field']);
    }

    #[Test]
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

        $queries = [$wizard->getSubject()->getQuery()];

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

    #[Test]
    public function it_uses_alias_correctly(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('random')
            ->allowedSorts(RandomSort::make('shuffle', 'random'));
        $wizard->build();

        $queries = [$wizard->getSubject()->getQuery()];

        $this->assertCount(1, $queries);
        $this->assertArrayHasKey('function_score', $queries[0]);
    }

    #[Test]
    public function it_returns_correct_type(): void
    {
        $sort = RandomSort::make('shuffle');

        $this->assertEquals('random', $sort->getType());
    }

    #[Test]
    public function it_uses_default_property_name(): void
    {
        $sort = RandomSort::make();

        $this->assertEquals('_random', $sort->getProperty());
        $this->assertEquals('_random', $sort->getName());
    }

    #[Test]
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

        $queries = [$wizard->getSubject()->getQuery()];
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

    #[Test]
    public function it_creates_separate_score_sorts_when_used_with_score_sort(): void
    {
        // Edge case: RandomSort adds _score sort, ScoreSort also adds _score sort
        // This tests the behavior when both are used together
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle,-relevance')
            ->allowedSorts(
                RandomSort::make('shuffle'),
                ScoreSort::make('relevance')
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        // Both sorts are added - this documents current behavior
        // RandomSort adds _score asc, ScoreSort adds _score desc
        $this->assertCount(2, $sorts);
        $this->assertEquals(['_score' => 'asc'], $sorts[0]);
        $this->assertEquals(['_score' => 'desc'], $sorts[1]);
    }

    #[Test]
    public function it_replaces_the_score_of_the_whole_query(): void
    {
        $wizard = $this
            ->createElasticWizardFromQuery(['filter' => ['q' => 'shoes', 'category' => 'x'], 'sort' => 'shuffle'])
            ->allowedFilters(ElasticFilter::match('title', 'q'), ElasticFilter::term('category'))
            ->allowedSorts(RandomSort::make('shuffle'))
            ->tapSearchBuilder(fn (SearchBuilder $builder) => $builder->query(Query::term('is_visible', true)));
        $wizard->build();

        $this->assertEquals([
            'function_score' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            ['match' => ['title' => ['query' => 'shoes']]],
                            ['term' => ['is_visible' => ['value' => true]]],
                        ],
                        'filter' => [['term' => ['category' => ['value' => 'x']]]],
                    ],
                ],
                'functions' => [['random_score' => new stdClass]],
                'boost_mode' => 'replace',
            ],
        ], $wizard->getSubject()->toArray()['body']['query']);
    }

    #[Test]
    public function it_keeps_a_query_set_on_the_builder_when_no_filter_applies(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('shuffle')
            ->allowedSorts(RandomSort::make('shuffle'))
            ->tapSearchBuilder(fn (SearchBuilder $builder) => $builder->query(Query::term('is_visible', true)));
        $wizard->build();

        $this->assertEquals([
            'function_score' => [
                'query' => ['term' => ['is_visible' => ['value' => true]]],
                'functions' => [['random_score' => new stdClass]],
                'boost_mode' => 'replace',
            ],
        ], $wizard->getSubject()->toArray()['body']['query']);
    }
}
