<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\PrefixFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class PrefixFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_builds_a_prefix_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['username' => 'john'])
            ->allowedFilters(PrefixFilter::make('username'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['prefix' => ['username' => ['value' => 'john']]], $queries[0]);
    }

    #[Test]
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['username' => ''])
            ->allowedFilters(PrefixFilter::make('username'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_resolves_the_property_name_via_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['user' => 'john'])
            ->allowedFilters(PrefixFilter::make('username', 'user'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['prefix' => ['username' => ['value' => 'john']]], $queries[0]);
    }

    #[Test]
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['username' => 'john'])
            ->allowedFilters(
                PrefixFilter::make('username')->withParameters(['case_insensitive' => true])
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'prefix' => [
                'username' => [
                    'value' => 'john',
                    'case_insensitive' => true,
                ],
            ],
        ], $queries[0]);
    }

    #[Test]
    public function it_rejects_array_input(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createElasticWizardWithFilters(['username' => ['john', 'jane']])
            ->allowedFilters(PrefixFilter::make('username'))
            ->build();
    }

    #[Test]
    public function it_ignores_empty_array_input(): void
    {
        // A blank multi-value parameter means "not applied", the same as
        // ?filter[username]= - only a value carrying several entries is an error.
        $wizard = $this
            ->createElasticWizardWithFilters(['username' => []])
            ->allowedFilters(PrefixFilter::make('username'));
        $wizard->build();

        $this->assertEmpty($this->getFilterQueries($wizard->boolQuery()));
    }
}
