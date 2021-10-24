<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Illuminate\Http\Request;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Values\Sort;
use Jackardios\ElasticQueryWizard\Handlers\Sorts\AbstractElasticSort;
use Jackardios\ElasticQueryWizard\Handlers\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use ReflectionClass;

/**
 * @group elastic
 * @group sort
 * @group elastic-sort
 */
class SortTest extends TestCase
{
    use AssertsCollectionSorting;

    /** @test */
    public function it_can_sort_a_query_ascending(): void
    {
        $wizard =$this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('name')
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_can_sort_a_query_descending(): void
    {
        $wizard =$this
            ->createQueryFromSortRequest('-name')
            ->setAllowedSorts('name')
            ->build();

        $this->assertEquals([["name" => "desc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_can_sort_a_query_by_alias(): void
    {
        $wizard =$this
            ->createQueryFromSortRequest('name-alias')
            ->setAllowedSorts([new FieldSort('name', 'name-alias')])
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_can_allow_a_descending_sort_by_still_sort_ascending(): void
    {
        $wizard =$this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('-name')
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_can_sort_by_sketchy_alias_if_its_an_allowed_sort(): void
    {
        $wizard =$this
            ->createQueryFromSortRequest('-sketchy<>sort')
            ->setAllowedSorts(new FieldSort('name', 'sketchy<>sort'))
            ->build();

        $this->assertEquals([["name" => "desc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_will_throw_an_exception_if_a_sort_property_is_not_allowed(): void
    {
        $this->expectException(InvalidSortQuery::class);

        $this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('id')
            ->build();
    }

    /** @test */
    public function it_wont_sort_if_no_sort_query_parameter_is_given(): void
    {
        $wizard =ElasticQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts('name')
            ->build();

        $this->assertEquals([], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_uses_default_sort_parameter_when_no_sort_was_requested(): void
    {
        $wizard =ElasticQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts('name')
            ->setDefaultSorts('name')
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_doesnt_use_the_default_sort_parameter_when_a_sort_was_requested(): void
    {
        $wizard =$this->createQueryFromSortRequest('id')
            ->setAllowedSorts('id')
            ->setDefaultSorts('name')
            ->build();

        $this->assertEquals([["id" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_allows_default_custom_sort_class_parameter(): void
    {
        $sortClass = new class('custom_name') extends AbstractElasticSort {
            public function handle($queryHandler, $queryBuilder, string $direction): void
            {
                $queryBuilder->sort('name', $direction);
            }
        };

        $wizard =ElasticQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts($sortClass)
            ->setDefaultSorts(new Sort('custom_name'))
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_uses_default_descending_sort_parameter(): void
    {
        $wizard =ElasticQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts('-name')
            ->setDefaultSorts('-name')
            ->build();

        $this->assertEquals([["name" => "desc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_allows_multiple_default_sort_parameters(): void
    {
        $sortClass = new class('custom_name') extends AbstractElasticSort {
            public function handle($queryHandler, $queryBuilder, string $direction): void
            {
                $queryBuilder->sort('name', $direction);
            }
        };

        $wizard =ElasticQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts($sortClass, 'id')
            ->setDefaultSorts('custom_name', new Sort('id', SortDirection::DESCENDING))
            ->build();

        $this->assertEquals([
            ["name" => "asc"],
            ["id" => "desc"]
        ], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_can_allow_multiple_sort_parameters(): void
    {
        $wizard =$this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts('id', 'name')
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_can_allow_multiple_sort_parameters_as_an_array(): void
    {
        $wizard =$this
            ->createQueryFromSortRequest('name')
            ->setAllowedSorts(['id', 'name'])
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_can_sort_by_multiple_columns(): void
    {
        $wizard =$this
            ->createQueryFromSortRequest('name,-id')
            ->setAllowedSorts('name', 'id')
            ->build();

        $this->assertEquals([
            ["name" => "asc"],
            ["id" => "desc"]
        ], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_can_sort_by_a_custom_sort_class(): void
    {
        $sortClass = new class('custom_name') extends AbstractElasticSort {
            public function handle($queryHandler, $queryBuilder, string $direction): void
            {
                $queryBuilder->sort('name', $direction);
            }
        };

        $wizard =$this
            ->createQueryFromSortRequest('custom_name')
            ->setAllowedSorts($sortClass)
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_resolves_queries_using_property_column_name(): void
    {
        $sort = new FieldSort('name', 'nickname');

        $wizard =$this
            ->createQueryFromSortRequest('nickname')
            ->setAllowedSorts($sort)
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_can_sort_descending_with_an_alias(): void
    {
        $wizard =$this->createQueryFromSortRequest('-exposed_property_name')
            ->setAllowedSorts(new FieldSort('name', 'exposed_property_name'))
            ->build();

        $this->assertEquals([["name" => "desc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function it_does_not_add_sort_clauses_multiple_times(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)
            ->setAllowedSorts('name')
            ->setDefaultSorts('name', '-name')
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function given_a_default_sort_a_sort_alias_will_still_be_resolved(): void
    {
        $wizard = $this->createQueryFromSortRequest('-joined')
            ->setDefaultSorts('name')
            ->setAllowedSorts(new FieldSort('created_at', 'joined'))
            ->build();

        $this->assertEquals([["created_at" => "desc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    /** @test */
    public function the_default_direction_of_an_allow_sort_can_be_set(): void
    {
        $sortClass = new class('custom_name') extends AbstractElasticSort {
            public function handle($queryHandler, $queryBuilder, string $direction): void
            {
                $queryBuilder->sort('name', $direction);
            }
        };

        $wizard = ElasticQueryWizard::for(TestModel::class, new Request())
            ->setAllowedSorts($sortClass)
            ->setDefaultSorts('-custom_name')
            ->build();

        $this->assertEquals([["name" => "desc"]], $this->getSorts($wizard->getHandler()->getSubject()));
    }

    protected function createQueryFromSortRequest(string $sort): ElasticQueryWizard
    {
        $request = new Request([
            'sort' => $sort,
        ]);

        return ElasticQueryWizard::for(TestModel::class, $request);
    }

    protected function getSorts(SearchRequestBuilder $wizard) {
        $reflection = new ReflectionClass($wizard);
        $property = $reflection->getProperty('sort');
        $property->setAccessible(true);
        return $property->getValue($wizard);
    }
}
