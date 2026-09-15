<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\RegexpFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class RegexpFilterQueryTest extends UnitTestCase
{
    #[Test]
    public function it_builds_a_regexp_query(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['username' => 'joh.*n'])
            ->allowedFilters(RegexpFilter::make('username'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['regexp' => ['username' => ['value' => 'joh.*n']]], $queries[0]);
    }

    #[Test]
    public function it_does_not_add_a_query_for_blank_value(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['username' => ''])
            ->allowedFilters(RegexpFilter::make('username'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertEmpty($queries);
    }

    #[Test]
    public function it_resolves_the_property_name_via_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['user' => 'joh.*'])
            ->allowedFilters(RegexpFilter::make('username', 'user'));
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals(['regexp' => ['username' => ['value' => 'joh.*']]], $queries[0]);
    }

    #[Test]
    public function it_applies_extra_parameters(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['username' => 'joh.*'])
            ->allowedFilters(
                RegexpFilter::make('username')->withParameters(['flags' => 'ALL', 'case_insensitive' => true])
            );
        $wizard->build();

        $queries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $queries);
        $this->assertEquals([
            'regexp' => [
                'username' => [
                    'value' => 'joh.*',
                    'flags' => 'ALL',
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
            ->createElasticWizardWithFilters(['username' => ['joh.*', 'jane.*']])
            ->allowedFilters(RegexpFilter::make('username'))
            ->build();
    }
}
