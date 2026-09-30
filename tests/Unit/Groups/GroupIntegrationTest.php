<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Groups;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Enums\BoolClause;
use Jackardios\ElasticQueryWizard\Exceptions\FilterNameConflict;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Contracts\QueryWizardInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Schema\ResourceSchema;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('group')]
class GroupIntegrationTest extends UnitTestCase
{
    #[Test]
    public function it_applies_bool_group_to_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'status' => 'active',
                'priority' => 'high',
            ])
            ->allowedFilters([
                ElasticGroup::bool('advanced')
                    ->minimumShouldMatch(1)
                    ->inFilter()
                    ->children([
                        ElasticFilter::term('status', 'status')->inShould(),
                        ElasticFilter::term('priority', 'priority')->inShould(),
                    ]),
            ]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertArrayHasKey('bool', $filterQueries[0]);
        $this->assertArrayHasKey('should', $filterQueries[0]['bool']);
        $this->assertCount(2, $filterQueries[0]['bool']['should']);
        $this->assertArrayHasKey('minimum_should_match', $filterQueries[0]['bool']);
    }

    #[Test]
    public function it_applies_nested_group_to_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'id' => '123',
                'search' => 'Main Street',
            ])
            ->allowedFilters([
                ElasticGroup::nested('sides')
                    ->inFilter()
                    ->children([
                        ElasticFilter::term('sides.id', 'id'),
                        ElasticFilter::match('sides.address', 'search')->inMust(),
                    ]),
            ]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertArrayHasKey('nested', $filterQueries[0]);
        $this->assertEquals('sides', $filterQueries[0]['nested']['path']);
    }

    #[Test]
    public function it_mixes_regular_filters_with_groups(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'category' => 'electronics',
                'status' => 'active',
                'priority' => 'high',
            ])
            ->allowedFilters([
                ElasticFilter::term('category'),
                ElasticGroup::bool('advanced')
                    ->minimumShouldMatch(1)
                    ->inFilter()
                    ->children([
                        ElasticFilter::term('status', 'status')->inShould(),
                        ElasticFilter::term('priority', 'priority')->inShould(),
                    ]),
            ]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        // Should have 2 filter queries: term for category and bool for advanced group
        $this->assertCount(2, $filterQueries);
    }

    #[Test]
    public function it_skips_group_when_no_child_values_provided(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'category' => 'electronics',
            ])
            ->allowedFilters([
                ElasticFilter::term('category'),
                ElasticGroup::bool('advanced')
                    ->children([
                        ElasticFilter::term('status', 'status')->inShould(),
                        ElasticFilter::term('priority', 'priority')->inShould(),
                    ]),
            ]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        // Should only have 1 filter query for category
        $this->assertCount(1, $filterQueries);
        $this->assertArrayHasKey('term', $filterQueries[0]);
    }

    #[Test]
    public function it_validates_child_filter_names_as_allowed(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'status' => 'active',
            ])
            ->allowedFilters([
                ElasticGroup::bool('advanced')
                    ->children([
                        ElasticFilter::term('status', 'status'),
                    ]),
            ]);

        $wizard->build();

        $this->assertSame(
            [['bool' => ['filter' => [['term' => ['status' => ['value' => 'active']]]]]]],
            $this->getFilterQueries($wizard->boolQuery())
        );
    }

    #[Test]
    public function it_applies_group_to_must_clause(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'status' => 'active',
            ])
            ->allowedFilters([
                ElasticGroup::bool('advanced')
                    ->inMust()
                    ->children([
                        ElasticFilter::term('status', 'status'),
                    ]),
            ]);

        $wizard->build();

        $mustQueries = $this->getMustQueries($wizard->boolQuery());
        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $mustQueries);
        $this->assertEmpty($filterQueries);
    }

    #[Test]
    public function it_applies_group_to_should_clause(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'status' => 'active',
            ])
            ->allowedFilters([
                ElasticGroup::bool('advanced')
                    ->inShould()
                    ->children([
                        ElasticFilter::term('status', 'status'),
                    ]),
            ]);

        $wizard->build();

        $shouldQueries = $this->getShouldQueries($wizard->boolQuery());
        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $shouldQueries);
        $this->assertEmpty($filterQueries);
    }

    /**
     * @return array<string, array{BoolClause, string}>
     */
    public static function innerGroupClauses(): array
    {
        return [
            'filter' => [BoolClause::Filter, 'filter'],
            'must' => [BoolClause::Must, 'must'],
            'should' => [BoolClause::Should, 'should'],
            'must_not' => [BoolClause::MustNot, 'must_not'],
        ];
    }

    #[Test]
    #[DataProvider('innerGroupClauses')]
    public function a_group_inside_a_group_goes_to_its_own_clause(BoolClause $clause, string $key): void
    {
        $innerGroup = ElasticGroup::bool('inner')->children([ElasticFilter::term('status', 'status')]);
        match ($clause) {
            BoolClause::Filter => $innerGroup->inFilter(),
            BoolClause::Must => $innerGroup->inMust(),
            BoolClause::Should => $innerGroup->inShould(),
            BoolClause::MustNot => $innerGroup->inMustNot(),
        };

        $wizard = $this
            ->createElasticWizardWithFilters(['status' => 'active'])
            ->allowedFilters(ElasticGroup::bool('outer')->children([$innerGroup]));
        $wizard->build();

        $this->assertSame(
            [['bool' => [$key => [['bool' => ['filter' => [['term' => ['status' => ['value' => 'active']]]]]]]]]],
            $this->getFilterQueries($wizard->boolQuery())
        );
    }

    #[Test]
    public function a_root_group_in_must_not_excludes_its_matches(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['status' => 'archived'])
            ->allowedFilters(
                ElasticGroup::bool('hidden')->inMustNot()->children([ElasticFilter::term('status', 'status')])
            );
        $wizard->build();

        $this->assertSame(
            [['bool' => ['filter' => [['term' => ['status' => ['value' => 'archived']]]]]]],
            $this->getMustNotQueries($wizard->boolQuery())
        );
        $this->assertEmpty($this->getFilterQueries($wizard->boolQuery()));
    }

    #[Test]
    public function it_applies_nested_groups_inside_bool_group(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'id' => '123',
            ])
            ->allowedFilters([
                ElasticGroup::bool('advanced')
                    ->inFilter()
                    ->children([
                        ElasticGroup::nested('sides')
                            ->inFilter()
                            ->children([
                                ElasticFilter::term('sides.id', 'id'),
                            ]),
                    ]),
            ]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertArrayHasKey('bool', $filterQueries[0]);
        $this->assertArrayHasKey('filter', $filterQueries[0]['bool']);
    }

    #[Test]
    public function it_applies_three_level_nested_groups(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'status' => 'active',
            ])
            ->allowedFilters([
                ElasticGroup::bool('level_1')
                    ->inFilter()
                    ->children([
                        ElasticGroup::bool('level_2')
                            ->inFilter()
                            ->children([
                                ElasticGroup::bool('level_3')
                                    ->inFilter()
                                    ->children([
                                        ElasticFilter::term('status', 'status'),
                                    ]),
                            ]),
                    ]),
            ]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertArrayHasKey('bool', $filterQueries[0]);

        $level2 = $filterQueries[0]['bool']['filter'][0];
        $level3 = $level2['bool']['filter'][0];
        $status = $level3['bool']['filter'][0]['term']['status']['value'] ?? null;

        $this->assertEquals('active', $status);
    }

    #[Test]
    public function it_rejects_group_name_as_filter_key(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createElasticWizardWithFilters([
                'advanced' => 'some_value',  // Group name, not a valid filter
            ])
            ->allowedFilters([
                ElasticGroup::bool('advanced')
                    ->children([
                        ElasticFilter::term('status', 'status'),
                    ]),
            ])
            ->build();
    }

    #[Test]
    public function it_deduplicates_filter_names_when_same_name_in_root_and_group(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'status' => 'active',
            ])
            ->allowedFilters([
                ElasticFilter::term('status'),  // Root level
                ElasticGroup::bool('advanced')
                    ->children([
                        ElasticFilter::term('status', 'status'),  // Same name in group
                    ]),
            ]);

        // Should not throw - filter name is deduplicated
        $wizard->build();

        // Group should take precedence (processed first in loop)
        // The root 'status' filter is skipped because it's in handledChildNames
        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertArrayHasKey('bool', $filterQueries[0]);
    }

    #[Test]
    public function a_root_filter_whose_default_a_group_leaf_would_drop_throws(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([])
            ->allowedFilters([
                ElasticFilter::term('status')->default('active'),
                ElasticGroup::bool('advanced')->children([ElasticFilter::term('status')]),
            ]);

        $this->expectException(FilterNameConflict::class);
        $this->expectExceptionMessage('Filter `status` has a default, but a group leaf of the same name reads its request key');

        $wizard->build();
    }

    #[Test]
    public function it_does_not_apply_sibling_filter_when_group_name_matches_filter_name(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'priority' => 'high',
            ])
            ->allowedFilters([
                ElasticGroup::bool('outer')
                    ->children([
                        ElasticFilter::term('status', 'status'),
                        ElasticGroup::bool('status')
                            ->children([
                                ElasticFilter::term('priority', 'priority'),
                            ]),
                    ]),
            ]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertArrayHasKey('bool', $filterQueries[0]);
        $this->assertArrayHasKey('filter', $filterQueries[0]['bool']);
        $this->assertCount(1, $filterQueries[0]['bool']['filter']);
        $this->assertArrayHasKey('bool', $filterQueries[0]['bool']['filter'][0]);

        $innerFilter = $filterQueries[0]['bool']['filter'][0]['bool']['filter'][0] ?? [];
        $this->assertEquals('high', $innerFilter['term']['priority']['value'] ?? null);
    }

    #[Test]
    public function it_supports_dot_notation_fields_with_alias_in_nested_group(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([
                'side_id' => '123',       // Alias for sides.id
                'side_search' => 'Main',  // Alias for sides.address
            ])
            ->allowedFilters([
                ElasticGroup::nested('sides')
                    ->inFilter()
                    ->children([
                        ElasticFilter::term('sides.id', 'side_id'),           // dot notation field, simple alias
                        ElasticFilter::match('sides.address', 'side_search'), // dot notation field, simple alias
                    ]),
            ]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertArrayHasKey('nested', $filterQueries[0]);
        $this->assertEquals('sides', $filterQueries[0]['nested']['path']);

        // Check inner bool query has both filters
        $innerBool = $filterQueries[0]['nested']['query']['bool'];
        $this->assertNotEmpty($innerBool);
    }

    #[Test]
    public function a_group_named_like_another_allowed_filter_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('More than one allowed filter is named `category`.');

        $this
            ->createElasticWizardWithFilters(['category' => 'books'])
            ->allowedFilters([
                ElasticFilter::term('category'),
                ElasticGroup::bool('category')->children([ElasticFilter::term('status')]),
            ])
            ->build();
    }

    #[Test]
    public function two_nested_groups_on_one_path_without_names_are_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('More than one allowed filter is named `comments`.');

        $this
            ->createElasticWizardWithFilters(['status' => 'open'])
            ->allowedFilters([
                ElasticGroup::nested('comments')->children([ElasticFilter::term('comments.status')->alias('status')]),
                ElasticGroup::nested('comments')->inMustNot()->children([ElasticFilter::term('comments.flag')->alias('flag')]),
            ])
            ->build();
    }

    #[Test]
    public function a_leaf_in_an_added_group_and_an_allowed_group_is_refused(): void
    {
        $this->expectException(FilterNameConflict::class);
        $this->expectExceptionMessage('Filters `status` are each in more than one group');

        $this
            ->createElasticWizardWithFilters(['status' => 'open'])
            ->allowedFilters(ElasticGroup::bool('a')->children([ElasticFilter::term('status')]))
            ->addAllowedFilters(ElasticGroup::bool('b')->inMustNot()->children([ElasticFilter::term('status')]))
            ->build();
    }

    #[Test]
    public function a_leaf_in_two_groups_is_refused(): void
    {
        $this->expectException(FilterNameConflict::class);
        $this->expectExceptionMessage('Filters `status` are each in more than one group');

        $this
            ->createElasticWizardWithFilters(['status' => 'open'])
            ->allowedFilters([
                ElasticGroup::bool('a')->children([ElasticFilter::term('status')]),
                ElasticGroup::bool('b')->inMustNot()->children([ElasticFilter::term('status')]),
            ])
            ->build();
    }

    #[Test]
    public function a_disallowed_group_does_not_conflict(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['status' => 'open'])
            ->allowedFilters([
                ElasticGroup::bool('a')->children([ElasticFilter::term('status')]),
                ElasticGroup::bool('b')->inMustNot()->children([ElasticFilter::term('status')]),
            ])
            ->disallowedFilters('b');
        $wizard->build();

        $this->assertSame(
            [['bool' => ['filter' => [['term' => ['status' => ['value' => 'open']]]]]]],
            $this->getFilterQueries($wizard->boolQuery())
        );
        $this->assertSame([], $this->getMustNotQueries($wizard->boolQuery()));
    }

    #[Test]
    public function a_schema_default_keyed_by_a_group_leaf_applies(): void
    {
        $wizard = $this->createElasticWizardWithFilters([])->schema($this->groupSchema(['status' => 'published']));
        $wizard->build();

        $this->assertSame(
            [['bool' => ['filter' => [['term' => ['status' => ['value' => 'published']]]]]]],
            $this->getFilterQueries($wizard->boolQuery())
        );
    }

    #[Test]
    public function a_schema_default_keyed_by_a_group_throws(): void
    {
        $wizard = $this->createElasticWizardWithFilters([])->schema($this->groupSchema(['a' => 'published']));

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Schema defaultFilters() names no allowed filter: `a`.');

        $wizard->build();
    }

    #[Test]
    public function disallowing_a_group_leaf_drops_its_schema_default(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['priority' => 'high'])
            ->schema($this->groupSchema(['status' => 'published']))
            ->disallowedFilters('status');
        $wizard->build();

        $this->assertSame(
            [['bool' => ['filter' => [['term' => ['priority' => ['value' => 'high']]]]]]],
            $this->getFilterQueries($wizard->boolQuery())
        );
    }

    #[Test]
    public function a_disallowed_group_does_not_conflict_with_schema_defaults(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters([])
            ->schema($this->groupSchema(['status' => 'published'], withSecondGroup: true))
            ->disallowedFilters('b');
        $wizard->build();

        $this->assertSame(
            [['bool' => ['filter' => [['term' => ['status' => ['value' => 'published']]]]]]],
            $this->getFilterQueries($wizard->boolQuery())
        );
    }

    /**
     * @param  array<string, mixed>  $defaults
     */
    private function groupSchema(array $defaults, bool $withSecondGroup = false): ResourceSchema
    {
        return new class($defaults, $withSecondGroup) extends ResourceSchema
        {
            /**
             * @param  array<string, mixed>  $defaults
             */
            public function __construct(private readonly array $defaults, private readonly bool $withSecondGroup) {}

            public function model(): string
            {
                return TestModel::class;
            }

            public function filters(QueryWizardInterface $wizard): array
            {
                return array_filter([
                    ElasticGroup::bool('a')->children([ElasticFilter::term('status'), ElasticFilter::term('priority')]),
                    $this->withSecondGroup ? ElasticGroup::bool('b')->inMustNot()->children([ElasticFilter::term('status')]) : null,
                ]);
            }

            public function defaultFilters(QueryWizardInterface $wizard): array
            {
                return $this->defaults;
            }
        };
    }
}
