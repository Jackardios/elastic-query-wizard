<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Illuminate\Http\Request;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticQuery;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\ElasticSort;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Support\Query;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\QueryParametersManager;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * What the wizard takes from inside a build callback, and when each kind of search builder change runs.
 */
#[Group('unit')]
class BuildCallbacksTest extends UnitTestCase
{
    #[Test]
    public function bool_query_called_from_a_tap_search_builder_callback_returns_the_bool_query_being_built(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['status' => 'x'])->allowedFilters(ElasticFilter::term('status'));
        $seen = [];
        $wizard->tapSearchBuilder(function (SearchBuilder $builder) use ($wizard, &$seen): void {
            $seen[] = $wizard->boolQuery() === $builder->boolQuery();
            $wizard->boolQuery()->addMust(Query::term('extra', 'y'));
        });

        $boolQuery = $wizard->build()->boolQuery();

        $this->assertSame([true], $seen);
        $this->assertSame([['term' => ['extra' => ['value' => 'y']]]], $this->getMustQueries($boolQuery));
        $this->assertSame([['term' => ['status' => ['value' => 'x']]]], $this->getFilterQueries($boolQuery));
    }

    #[Test]
    public function bool_query_called_while_the_wizard_builds_does_not_lock_the_configuration(): void
    {
        $wizard = $this->createElasticWizardWithFilters([]);
        $runs = 0;
        $wizard->tapSearchBuilder(function () use ($wizard, &$runs): void {
            $runs++;
            $wizard->boolQuery()->addMust(Query::term('extra', 'y'));
        });
        $wizard->build();

        $boolQuery = $wizard->allowedSorts('name')->build()->boolQuery();

        $this->assertSame(2, $runs);
        $this->assertSame([['term' => ['extra' => ['value' => 'y']]]], $this->getMustQueries($boolQuery));
    }

    #[Test]
    public function bool_query_is_readable_from_a_core_tap_and_from_a_callback_filter(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['flag' => '1']);
        $wizard
            ->tap(function () use ($wizard): void {
                $wizard->boolQuery()->addFilter(Query::term('from_tap', 1));
            })
            ->allowedFilters(ElasticFilter::callback('flag', function () use ($wizard): void {
                $wizard->boolQuery()->addFilter(Query::term('from_filter', 1));
            }));

        $this->assertSame(
            [['term' => ['from_tap' => ['value' => 1]]], ['term' => ['from_filter' => ['value' => 1]]]],
            $this->getFilterQueries($wizard->build()->boolQuery())
        );
    }

    #[Test]
    public function a_search_builder_method_that_builds_the_wizard_names_itself_when_called_from_a_callback(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['flag' => '1']);
        $wizard->allowedFilters(ElasticFilter::callback('flag', function () use ($wizard): void {
            $wizard->getSort();
        }));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(
            'getSort() cannot be called on the wizard while it builds: it runs on the built search. '
            .'Call it on the SearchBuilder the callback receives.'
        );

        $wizard->build();
    }

    #[Test]
    public function a_failed_build_leaves_bool_query_building_the_wizard_again(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['status' => 'x']);

        try {
            $wizard->build();
            $this->fail('The build accepted a filter that is not allowed.');
        } catch (InvalidFilterQuery) {
        }

        $boolQuery = $wizard->allowedFilters(ElasticFilter::term('status'))->boolQuery();

        $this->assertSame([['term' => ['status' => ['value' => 'x']]]], $this->getFilterQueries($boolQuery));
    }

    #[Test]
    public function a_fluent_call_before_the_build_comes_ahead_of_the_requested_sorts_and_after_it_replaces_them(): void
    {
        $before = $this->createElasticWizardWithSorts('-name')->allowedSorts('name');
        $before->sortRaw([['_score' => 'desc']]);

        $after = $this->createElasticWizardWithSorts('-name')->allowedSorts('name');
        $after->build();
        $after->sortRaw([['_score' => 'desc']]);

        $this->assertSame([['_score' => 'desc'], ['name' => 'desc']], $before->build()->getSort());
        $this->assertSame([['_score' => 'desc']], $after->build()->getSort());
    }

    #[Test]
    public function tap_built_search_runs_after_the_filters_and_sorts(): void
    {
        $wizard = $this
            ->createElasticWizardFromQuery(['filter' => ['status' => 'x'], 'sort' => '-name'])
            ->allowedFilters(ElasticFilter::term('status'))
            ->allowedSorts('name');
        $seen = [];
        $wizard
            ->tapSearchBuilder(function (SearchBuilder $builder) use (&$seen): void {
                $seen['start'] = [$builder->getBoolQuery()?->hasClauses() ?? false, $builder->getSort()];
            })
            ->tapBuiltSearch(function (SearchBuilder $builder) use (&$seen): void {
                $seen['end'] = [$builder->getBoolQuery()?->hasClauses() ?? false, $builder->getSort()];
            });
        $wizard->build();

        $this->assertSame(['start' => [false, []], 'end' => [true, [['name' => 'desc']]]], $seen);
    }

    #[Test]
    public function tap_built_search_wraps_the_filtered_query_on_every_build(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['status' => 'x'])
            ->allowedFilters(ElasticFilter::term('status'))
            ->tapBuiltSearch(function (SearchBuilder $builder): void {
                $builder->query(
                    ElasticQuery::functionScore(clone $builder->boolQuery())
                        ->addFunction(['field_value_factor' => ['field' => 'views']])
                )->clearBoolQuery();
            });

        $expected = ['function_score' => [
            'query' => ['bool' => ['filter' => [['term' => ['status' => ['value' => 'x']]]]]],
            'functions' => [['field_value_factor' => ['field' => 'views']]],
        ]];

        $this->assertSame($expected, $wizard->build()->toArray()['body']['query']);
        $this->assertSame($expected, $wizard->allowedSorts('name')->build()->toArray()['body']['query']);
    }

    #[Test]
    public function tap_built_search_on_a_built_wizard_runs_at_the_next_build(): void
    {
        $wizard = $this->createElasticWizardWithSorts('-name')->allowedSorts('name');
        $wizard->build();
        $runs = 0;
        $wizard->tapBuiltSearch(function (SearchBuilder $builder) use (&$runs): void {
            $runs++;
            $builder->sortRaw([['_score' => 'desc'], ...$builder->getSort()]);
        });

        $this->assertSame(0, $runs);
        $this->assertSame([['_score' => 'desc'], ['name' => 'desc']], $wizard->build()->getSort());
        $this->assertSame(1, $runs);
    }

    #[Test]
    public function tap_built_search_takes_any_callable_and_sees_the_bool_query_through_the_wizard(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['status' => 'x'])->allowedFilters(ElasticFilter::term('status'));
        $seen = null;
        $wizard->tapBuiltSearch(function () use ($wizard, &$seen): void {
            $seen = $wizard->boolQuery()->toArray();
        });
        $wizard->build();

        $this->assertSame(['bool' => ['filter' => [['term' => ['status' => ['value' => 'x']]]]]], $seen);
    }

    #[Test]
    public function tap_built_search_throws_while_the_wizard_builds_and_after_the_built_search_was_changed(): void
    {
        $building = $this->createElasticWizardWithFilters([]);
        $building->tapSearchBuilder(fn () => $building->tapBuiltSearch(fn () => null));

        try {
            $building->build();
            $this->fail('tapBuiltSearch() was accepted while the wizard builds.');
        } catch (LogicException $exception) {
            $this->assertSame('The wizard cannot be reconfigured while it builds.', $exception->getMessage());
        }

        $changed = $this->createElasticWizardWithFilters([]);
        $changed->boolQuery()->addMust(Query::term('extra', 'y'));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('the rebuild would drop the change');

        $changed->tapBuiltSearch(fn () => null);
    }

    #[Test]
    public function a_random_sort_and_a_tap_built_search_callback_both_see_the_filters(): void
    {
        $wizard = $this
            ->createElasticWizardFromQuery(['filter' => ['status' => 'x'], 'sort' => 'shuffle'])
            ->allowedFilters(ElasticFilter::term('status'))
            ->allowedSorts(ElasticSort::random('shuffle'))
            ->tapBuiltSearch(fn (SearchBuilder $builder) => $builder->trackScores(true));

        $body = $wizard->build()->toArray()['body'];

        $this->assertSame(
            ['bool' => ['filter' => [['term' => ['status' => ['value' => 'x']]]]]],
            $body['query']['function_score']['query']
        );
        $this->assertTrue($body['track_scores']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function placesACloneCanBeMadeIn(): array
    {
        return [
            'a core tap' => ['tap'],
            'a tapSearchBuilder callback' => ['tapSearchBuilder'],
            'a callback filter' => ['filter'],
            'a tapBuiltSearch callback' => ['tapBuiltSearch'],
        ];
    }

    #[Test]
    #[DataProvider('placesACloneCanBeMadeIn')]
    public function a_clone_made_inside_a_build_callback_builds_the_whole_search_on_its_own(string $place): void
    {
        $make = function (?\Closure $duringBuild = null) use ($place): ElasticQueryWizard {
            $run = static function () use ($duringBuild): void {
                if ($duringBuild !== null) {
                    $duringBuild();
                }
            };
            $wizard = ElasticQueryWizard::for(TestModel::class, new QueryParametersManager(new Request([
                'filter' => ['status' => 'x', 'flag' => '1'],
                'sort' => '-name',
            ])));

            return $wizard
                ->tapSearchBuilder(static fn (SearchBuilder $builder) => $builder->boolQuery()->addMust(Query::term('extra', 'y')))
                ->tap(fn () => $place === 'tap' ? $run() : null)
                ->tapSearchBuilder(fn () => $place === 'tapSearchBuilder' ? $run() : null)
                ->tapBuiltSearch(fn () => $place === 'tapBuiltSearch' ? $run() : null)
                ->allowedFilters(
                    ElasticFilter::callback('flag', fn () => $place === 'filter' ? $run() : null),
                    ElasticFilter::term('status'),
                )
                ->allowedSorts('name');
        };
        $expected = $make()->build()->toArray();
        $this->assertSame([['term' => ['status' => ['value' => 'x']]]], $expected['body']['query']['bool']['filter']);

        $clone = null;
        $wizard = null;
        $wizard = $make(function () use (&$wizard, &$clone): void {
            $clone ??= clone $wizard;
        });

        $this->assertSame($expected, $wizard->build()->toArray());
        $this->assertInstanceOf(ElasticQueryWizard::class, $clone);
        $this->assertSame($expected, $clone->build()->toArray());
        $this->assertNotSame($wizard->getSubject(), $clone->getSubject());
        $this->assertNotSame($wizard->getSubject()->boolQuery(), $clone->getSubject()->boolQuery());

        $clone->boolQuery()->addMust(Query::term('only_clone', 1));
        $this->assertSame($expected, $wizard->build()->toArray());

        $wizard->boolQuery()->addMust(Query::term('only_original', 1));
        $this->assertSame(
            [['term' => ['extra' => ['value' => 'y']]], ['term' => ['only_clone' => ['value' => 1]]]],
            $this->getMustQueries($clone->build()->boolQuery())
        );

        // A clone still open to configuration does not reconfigure the original.
        $second = null;
        $source = null;
        $source = $make(function () use (&$source, &$second): void {
            $second ??= clone $source;
        });
        $source->build();
        $second->disallowedFilters('status');

        $this->assertSame($expected, $source->build()->toArray());
    }

    /**
     * @return array<string, array{\Closure(ElasticQueryWizard): mixed, string}>
     */
    public static function methodsThatBuildTheWizard(): array
    {
        return [
            'paginate' => [
                static fn (ElasticQueryWizard $wizard) => $wizard->paginate(),
                'paginate() cannot be called on the wizard while it builds: it runs on the built search. '
                .'Call it on the SearchBuilder the callback receives.',
            ],
            'count' => [
                static fn (ElasticQueryWizard $wizard) => $wizard->count(),
                'count() cannot be called on the wizard while it builds: it runs on the built search. '
                .'Call it on the SearchBuilder the callback receives.',
            ],
        ];
    }

    #[Test]
    #[DataProvider('methodsThatBuildTheWizard')]
    public function a_method_that_builds_the_wizard_called_from_a_callback_names_itself(\Closure $call, string $message): void
    {
        foreach (['tapSearchBuilder', 'tapBuiltSearch'] as $hook) {
            $wizard = $this->createElasticWizardWithFilters([]);
            $wizard->{$hook}(static fn () => $call($wizard));

            try {
                $wizard->build();
                $this->fail("No exception from a {$hook}() callback.");
            } catch (LogicException $exception) {
                $this->assertSame($message, $exception->getMessage());
            }

            // The failed build leaves the wizard open to configuration.
            $this->assertSame($wizard, $wizard->allowedSorts('name'));
        }
    }

    #[Test]
    public function a_wrapper_set_in_tap_built_search_repeats_the_filters_unless_the_bool_query_is_cleared(): void
    {
        $wrap = static fn (SearchBuilder $builder): SearchBuilder => $builder->query(
            ['function_score' => ['query' => $builder->boolQuery()->toArray(), 'functions' => []]]
        );
        $filtered = ['bool' => ['filter' => [['term' => ['status' => ['value' => 'x']]]]]];
        $wrapped = ['function_score' => ['query' => $filtered, 'functions' => []]];
        $make = fn (): ElasticQueryWizard => $this
            ->createElasticWizardWithFilters(['status' => 'x'])
            ->allowedFilters(ElasticFilter::term('status'));

        $cleared = $make()->tapBuiltSearch(static fn (SearchBuilder $builder) => $wrap($builder)->clearBoolQuery());
        $kept = $make()->tapBuiltSearch($wrap);

        $this->assertSame($wrapped, $cleared->build()->toArray()['body']['query']);
        $this->assertSame(
            ['bool' => ['must' => [$wrapped], 'filter' => $filtered['bool']['filter']]],
            $kept->build()->toArray()['body']['query']
        );
    }
}
