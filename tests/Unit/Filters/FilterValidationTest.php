<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('unit')]
#[Group('filter')]
class FilterValidationTest extends UnitTestCase
{
    #[Test]
    public function it_throws_for_invalid_filter(): void
    {
        $this->expectException(InvalidFilterQuery::class);

        $this
            ->createElasticWizardWithFilters(['name' => 'John'])
            ->allowedFilters('id')
            ->build();
    }

    #[Test]
    public function the_exception_contains_unknown_and_allowed_filters(): void
    {
        $exception = new InvalidFilterQuery(collect(['unknown']), collect(['allowed']));

        $this->assertEquals(['unknown'], $exception->unknownFilters->all());
        $this->assertEquals(['allowed'], $exception->allowedFilters->all());
    }

    #[Test]
    public function it_sets_property_to_name_by_default(): void
    {
        $filter = TermFilter::make('property_name');

        $this->assertEquals($filter->getName(), $filter->getProperty());
    }

    #[Test]
    public function it_allows_valid_filters_without_throwing(): void
    {
        $wizard = $this
            ->createElasticWizardWithFilters(['name' => 'John'])
            ->allowedFilters('name', 'id');
        $wizard->build();

        $this->assertNotNull($wizard);
    }
}
