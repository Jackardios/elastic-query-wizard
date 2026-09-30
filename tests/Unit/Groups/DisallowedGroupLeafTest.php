<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Groups;

use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Groups\BoolGroup;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * disallowedFilters() removes a filter inside a group as it removes one at the root.
 */
#[Group('unit')]
#[Group('group')]
class DisallowedGroupLeafTest extends UnitTestCase
{
    #[Test]
    public function a_disallowed_leaf_is_not_a_valid_request_key(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this->createElasticWizardWithFilters(['internal_flag' => '1'])
            ->allowedFilters($this->group())
            ->disallowedFilters('internal_flag')
            ->build();
    }

    #[Test]
    public function a_disallowed_leaf_drops_its_default_and_keeps_its_siblings(): void
    {
        $wizard = $this->createElasticWizardWithFilters(['status' => 'active'])
            ->allowedFilters($this->group())
            ->disallowedFilters('internal_flag');
        $wizard->build();

        $this->assertSame(
            [['bool' => ['filter' => [['term' => ['status' => ['value' => 'active']]]]]]],
            $this->getFilterQueries($wizard->boolQuery())
        );
    }

    #[Test]
    public function a_disallowed_leaf_is_ignored_when_the_exception_is_disabled(): void
    {
        config()->set('query-wizard.ignore_unknown.filters', true);

        $wizard = $this->createElasticWizardWithFilters(['internal_flag' => '1'])
            ->allowedFilters($this->group())
            ->disallowedFilters('internal_flag');
        $wizard->build();

        $this->assertSame([], $this->getFilterQueries($wizard->boolQuery()));
    }

    private function group(): BoolGroup
    {
        return ElasticGroup::bool('g')->children([
            ElasticFilter::term('status'),
            ElasticFilter::term('internal_flag')->default('0'),
        ]);
    }
}
