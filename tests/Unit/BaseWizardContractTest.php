<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Guards the contract between this package and laravel-query-wizard.
 *
 * These cover behaviour that used to diverge because the wizard reimplemented
 * the base build and filter pipeline instead of extending it.
 */
#[Group('unit')]
#[Group('contract')]
class BaseWizardContractTest extends UnitTestCase
{
    #[Test]
    public function passthrough_values_are_readable_when_groups_are_registered(): void
    {
        $values = $this
            ->createElasticWizardWithFilters([
                'token' => 'abc',
                'status' => 'active',
            ])
            ->allowedFilters([
                ElasticFilter::passthrough('token'),
                ElasticGroup::bool('advanced')->children([
                    ElasticFilter::term('status', 'status'),
                ]),
            ])
            ->getPassthroughFilters();

        $this->assertSame(['token' => 'abc'], $values->all());
    }

    #[Test]
    public function passthrough_filters_do_not_reach_the_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['token' => 'abc'])
            ->allowedFilters([ElasticFilter::passthrough('token')]);

        $wizard->build();

        $this->assertTrue($wizard->getSubject()->boolQuery()->isEmpty());
    }

    #[Test]
    public function filter_value_shape_is_validated_through_the_base_pipeline(): void
    {
        $this->expectException(\Jackardios\QueryWizard\Exceptions\InvalidFilterQuery::class);

        $this
            ->createElasticWizardWithFilters(['name' => ['deep' => ['a' => 'b']]])
            ->allowedFilters([ElasticFilter::term('name')])
            ->build();
    }

    #[Test]
    public function group_child_value_shape_is_validated_too(): void
    {
        $this->expectException(\Jackardios\QueryWizard\Exceptions\InvalidFilterQuery::class);

        $this
            ->createElasticWizardWithFilters(['status' => ['deep' => ['a' => 'b']]])
            ->allowedFilters([
                ElasticGroup::bool('advanced')->children([
                    ElasticFilter::term('status', 'status'),
                ]),
            ])
            ->build();
    }

    #[Test]
    public function resource_key_follows_the_public_naming_convention(): void
    {
        config()->set('query-wizard.naming.convert_parameters_to_snake_case', true);

        $wizard = $this->createElasticWizardFromQuery([], TestModel::class);

        $this->assertSame('test_model', $wizard->getResourceKey());
    }

    #[Test]
    public function resource_key_stays_camel_case_by_default(): void
    {
        $wizard = $this->createElasticWizardFromQuery([], TestModel::class);

        $this->assertSame('testModel', $wizard->getResourceKey());
    }

    /**
     * resolveShadowedFilterNames() matches group children against the keys of
     * getEffectiveFilters(), which the base package normalizes. Should that ever
     * change, the two sides would stop lining up and the root-level filter would
     * be applied on top of the group.
     */
    #[Test]
    public function a_group_child_shadows_its_root_level_twin_under_name_normalization(): void
    {
        config()->set('query-wizard.naming.convert_parameters_to_snake_case', true);

        $wizard = $this
            ->createElasticWizardWithFilters(['first_name' => 'john'])
            ->allowedFilters([
                ElasticFilter::term('firstName'),
                ElasticGroup::bool('advanced')->children([
                    ElasticFilter::term('firstName', 'firstName'),
                ]),
            ]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(
            1,
            $filterQueries,
            'The group owns the request key, so the root-level filter must not be applied a second time.'
        );
        $this->assertArrayHasKey('bool', $filterQueries[0]);
    }

    /**
     * `?filter[x][]=` reaches the filter as [null]. Whether that is ignored or
     * rejected must not depend on which filter happens to be registered, or a UI
     * serialising an empty multiselect gets a 400 from one field and a 200 from
     * the next.
     */
    #[Test]
    public function a_blank_multi_value_parameter_is_ignored_by_every_filter_shape(): void
    {
        $filters = [
            'scalar-only' => ElasticFilter::prefix('name'),
            'scalar-or-list' => ElasticFilter::term('name'),
            'date-range' => ElasticFilter::dateRange('name'),
        ];

        foreach ($filters as $label => $filter) {
            $wizard = $this
                ->createElasticWizardWithFilters(['name' => [null]])
                ->allowedFilters([$filter]);

            $wizard->build();

            $this->assertTrue(
                $wizard->getSubject()->boolQuery()->isEmpty(),
                "The {$label} filter must ignore a blank multi-value parameter."
            );
        }
    }

    #[Test]
    public function clone_of_a_built_wizard_rebuilds_its_own_state(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'john'])
            ->allowedFilters([ElasticFilter::term('name')]);

        $wizard->build();

        $clone = clone $wizard;
        $clone->build();

        $this->assertEquals(
            $wizard->getSubject()->boolQuery()->toArray(),
            $clone->getSubject()->boolQuery()->toArray(),
            'A clone must rebuild the same query as the wizard it was cloned from.'
        );
    }
}
