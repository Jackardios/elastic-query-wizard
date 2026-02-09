<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic\Sorts;

use Illuminate\Http\Request;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\Tests\Concerns\AssertsCollectionSorting;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Sorts\AbstractSort;
use Jackardios\QueryWizard\Values\Sort;

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
            ->createElasticWizardWithSorts('name')
            ->allowedSorts('name')
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_can_sort_a_query_descending(): void
    {
        $wizard =$this
            ->createElasticWizardWithSorts('-name')
            ->allowedSorts('name')
            ->build();

        $this->assertEquals([["name" => "desc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_can_sort_a_query_by_alias(): void
    {
        $wizard =$this
            ->createElasticWizardWithSorts('name-alias')
            ->allowedSorts([FieldSort::make('name', 'name-alias')])
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_can_allow_a_descending_sort_by_still_sort_ascending(): void
    {
        $wizard =$this
            ->createElasticWizardWithSorts('name')
            ->allowedSorts('-name')
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_can_sort_by_sketchy_alias_if_its_an_allowed_sort(): void
    {
        $wizard =$this
            ->createElasticWizardWithSorts('-sketchy<>sort')
            ->allowedSorts(FieldSort::make('name', 'sketchy<>sort'))
            ->build();

        $this->assertEquals([["name" => "desc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_will_throw_an_exception_if_a_sort_property_is_not_allowed(): void
    {
        $this->expectException(InvalidSortQuery::class);

        $this
            ->createElasticWizardWithSorts('name')
            ->allowedSorts('id')
            ->build();
    }

    /** @test */
    public function it_wont_sort_if_no_sort_query_parameter_is_given(): void
    {
        $wizard =$this->createElasticWizardFromQuery()
            ->allowedSorts('name')
            ->build();

        $this->assertEquals([], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_uses_default_sort_parameter_when_no_sort_was_requested(): void
    {
        $wizard =$this->createElasticWizardFromQuery()
            ->allowedSorts('name')
            ->defaultSorts('name')
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_doesnt_use_the_default_sort_parameter_when_a_sort_was_requested(): void
    {
        $wizard =$this->createElasticWizardWithSorts('id')
            ->allowedSorts('id')
            ->defaultSorts('name')
            ->build();

        $this->assertEquals([["id" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_allows_default_custom_sort_class_parameter(): void
    {
        $sortClass = $this->createCustomSort();

        $wizard =$this->createElasticWizardFromQuery()
            ->allowedSorts($sortClass)
            ->defaultSorts(new Sort('custom_name'))
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_uses_default_descending_sort_parameter(): void
    {
        $wizard =$this->createElasticWizardFromQuery()
            ->allowedSorts('-name')
            ->defaultSorts('-name')
            ->build();

        $this->assertEquals([["name" => "desc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_allows_multiple_default_sort_parameters(): void
    {
        $sortClass = $this->createCustomSort();

        $wizard =$this->createElasticWizardFromQuery()
            ->allowedSorts($sortClass, 'id')
            ->defaultSorts('custom_name', new Sort('id', SortDirection::DESCENDING))
            ->build();

        $this->assertEquals([
            ["name" => "asc"],
            ["id" => "desc"]
        ], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_can_allow_multiple_sort_parameters(): void
    {
        $wizard =$this
            ->createElasticWizardWithSorts('name')
            ->allowedSorts('id', 'name')
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_can_allow_multiple_sort_parameters_as_an_array(): void
    {
        $wizard =$this
            ->createElasticWizardWithSorts('name')
            ->allowedSorts(['id', 'name'])
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_can_sort_by_multiple_columns(): void
    {
        $wizard =$this
            ->createElasticWizardWithSorts('name,-id')
            ->allowedSorts('name', 'id')
            ->build();

        $this->assertEquals([
            ["name" => "asc"],
            ["id" => "desc"]
        ], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_can_sort_by_a_custom_sort_class(): void
    {
        $sortClass = $this->createCustomSort();

        $wizard =$this
            ->createElasticWizardWithSorts('custom_name')
            ->allowedSorts($sortClass)
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_resolves_queries_using_property_column_name(): void
    {
        $sort = FieldSort::make('name', 'nickname');

        $wizard =$this
            ->createElasticWizardWithSorts('nickname')
            ->allowedSorts($sort)
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_can_sort_descending_with_an_alias(): void
    {
        $wizard =$this->createElasticWizardWithSorts('-exposed_property_name')
            ->allowedSorts(FieldSort::make('name', 'exposed_property_name'))
            ->build();

        $this->assertEquals([["name" => "desc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_does_not_add_sort_clauses_multiple_times(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)
            ->allowedSorts('name')
            ->defaultSorts('name', '-name')
            ->build();

        $this->assertEquals([["name" => "asc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function given_a_default_sort_a_sort_alias_will_still_be_resolved(): void
    {
        $wizard = $this->createElasticWizardWithSorts('-joined')
            ->defaultSorts('name')
            ->allowedSorts(FieldSort::make('created_at', 'joined'))
            ->build();

        $this->assertEquals([["created_at" => "desc"]], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function the_default_direction_of_an_allow_sort_can_be_set(): void
    {
        $sortClass = $this->createCustomSort();

        $wizard = $this->createElasticWizardFromQuery()
            ->allowedSorts($sortClass)
            ->defaultSorts('-custom_name')
            ->build();

        $this->assertEquals([["name" => "desc"]], $this->getSorts($wizard->getSubject()));
    }

    private function createCustomSort(): AbstractSort
    {
        return new class('custom_name') extends AbstractSort {
            public function __construct(string $property, ?string $alias = null)
            {
                parent::__construct($property, $alias);
            }

            public static function make(string $property, ?string $alias = null): static
            {
                return new static($property, $alias);
            }

            public function getType(): string
            {
                return 'custom';
            }

            public function apply(mixed $subject, string $direction): mixed
            {
                $subject->sort('name', $direction);

                return $subject;
            }
        };
    }

    protected function getSorts(SearchBuilder $builder): array
    {
        return $builder->getSort();
    }
}
