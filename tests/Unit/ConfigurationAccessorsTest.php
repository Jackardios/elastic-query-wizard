<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Exceptions\FilterNameConflict;
use Jackardios\ElasticQueryWizard\Filters\MatchFilter;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Groups\BoolGroup;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Reading what the wizard accepts, through laravel-query-wizard's getters and getAllowedLeafFilters().
 */
#[Group('unit')]
class ConfigurationAccessorsTest extends UnitTestCase
{
    #[Test]
    public function allowed_filters_list_a_group_under_its_own_name_and_leaf_filters_under_the_request_keys(): void
    {
        $wizard = $this->createElasticWizardFromQuery()->allowedFilters(
            'status',
            ElasticGroup::bool('search')->children([
                ElasticFilter::match('title', 'q'),
                ElasticGroup::nested('comments')->children([ElasticFilter::term('comments.author', 'author')]),
            ]),
        );

        $this->assertSame(['status', 'search'], array_keys($wizard->getAllowedFilters()));
        $this->assertInstanceOf(BoolGroup::class, $wizard->getAllowedFilters()['search']);

        $leaves = $wizard->getAllowedLeafFilters();

        $this->assertSame(['status', 'q', 'author'], array_keys($leaves));
        $this->assertInstanceOf(TermFilter::class, $leaves['status']);
        $this->assertInstanceOf(MatchFilter::class, $leaves['q']);
        $this->assertSame('comments.author', $leaves['author']->getProperty());
    }

    #[Test]
    public function leaf_filters_leave_out_what_disallowed_filters_removes(): void
    {
        $wizard = $this->createElasticWizardFromQuery()
            ->allowedFilters(
                'status',
                'category',
                ElasticGroup::bool('search')->children([ElasticFilter::match('title', 'q'), ElasticFilter::term('tag')]),
                ElasticGroup::bool('other')->children([ElasticFilter::term('kind')]),
            )
            ->disallowedFilters('category', 'tag', 'other');

        $this->assertSame(['status', 'q'], array_keys($wizard->getAllowedLeafFilters()));
    }

    #[Test]
    public function leaf_filters_are_keyed_as_the_request_is_read(): void
    {
        config()->set('query-wizard.naming.convert_parameters_to_snake_case', true);

        $wizard = $this->createElasticWizardFromQuery()->allowedFilters(
            ElasticFilter::term('status', 'postStatus'),
            ElasticGroup::bool('search')->children([ElasticFilter::match('title', 'searchText')]),
        );

        $this->assertSame(['post_status', 'search_text'], array_keys($wizard->getAllowedLeafFilters()));
    }

    #[Test]
    public function a_group_leaf_takes_the_key_of_a_root_filter_of_the_same_name(): void
    {
        $wizard = $this->createElasticWizardFromQuery()->allowedFilters(
            ElasticFilter::term('status'),
            ElasticGroup::bool('search')->children([ElasticFilter::match('status_text', 'status')]),
        );

        $leaves = $wizard->getAllowedLeafFilters();

        $this->assertSame(['status'], array_keys($leaves));
        $this->assertInstanceOf(MatchFilter::class, $leaves['status']);
    }

    #[Test]
    public function a_group_leaf_takes_the_key_of_a_root_filter_declared_after_the_group(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['status' => 'x'])->allowedFilters(
            ElasticGroup::bool('search')->children([ElasticFilter::match('status_text', 'status')]),
            ElasticFilter::term('status'),
            ElasticFilter::term('kind'),
        );

        $leaves = $wizard->getAllowedLeafFilters();

        $this->assertSame(['status', 'kind'], array_keys($leaves));
        $this->assertInstanceOf(MatchFilter::class, $leaves['status']);
        $this->assertSame(
            ['bool' => ['filter' => [['bool' => ['must' => [['match' => ['status_text' => ['query' => 'x']]]]]]]]],
            $wizard->build()->toArray()['body']['query']
        );
    }

    #[Test]
    public function leaf_filters_report_a_leaf_in_two_groups_as_the_build_does(): void
    {
        $wizard = $this->createElasticWizardFromQuery()->allowedFilters(
            ElasticGroup::bool('a')->children([ElasticFilter::term('status')]),
            ElasticGroup::bool('b')->children([ElasticFilter::term('status')]),
        );

        $this->expectException(FilterNameConflict::class);

        $wizard->getAllowedLeafFilters();
    }

    #[Test]
    public function the_getters_do_not_build_the_wizard_and_follow_later_configuration(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['nope' => 'x'])->allowedFilters('status');

        $this->assertSame(['status'], array_keys($wizard->getAllowedLeafFilters()));
        $this->assertSame(['status', 'nope'], array_keys($wizard->addAllowedFilters('nope')->getAllowedLeafFilters()));
        $this->assertSame([['term' => ['nope' => ['value' => 'x']]]], $this->getFilterQueries($wizard->build()->boolQuery()));
    }

    #[Test]
    public function requested_filter_names_are_the_leaves_the_request_names(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['q' => 'text', 'status' => 'x', 'unknown' => '1'])
            ->allowedFilters('status', ElasticGroup::bool('search')->children([ElasticFilter::match('title', 'q')]));

        $this->assertSame(['q', 'status', 'unknown'], $wizard->getRequestedFilterNames());
    }

    #[Test]
    public function leaf_filters_are_copies_at_the_root_and_inside_a_group(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['q' => 'long text', 'name' => 'long text'])
            ->allowedFilters(
                ElasticFilter::match('name'),
                ElasticGroup::bool('search')->children([
                    ElasticFilter::match('title', 'q'),
                    ElasticGroup::nested('comments')->children([ElasticFilter::match('comments.body', 'body')]),
                ]),
            );

        foreach ($wizard->getAllowedLeafFilters() as $leaf) {
            $this->assertInstanceOf(MatchFilter::class, $leaf);
            $leaf->maxLength(3);
        }

        $this->assertNotSame($wizard->getAllowedLeafFilters()['q'], $wizard->getAllowedLeafFilters()['q']);
        $this->assertNotSame($wizard->getAllowedLeafFilters()['body'], $wizard->getAllowedLeafFilters()['body']);
        $this->assertTrue($wizard->build()->hasBoolQuery());
    }
}
