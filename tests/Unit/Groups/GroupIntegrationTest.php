<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Groups;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;

/**
 * @group unit
 * @group group
 */
class GroupIntegrationTest extends UnitTestCase
{
    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

        // Should not throw an exception - 'status' is allowed via the group
        $wizard->build();

        $this->assertTrue(true);
    }

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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

    /** @test */
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
}
