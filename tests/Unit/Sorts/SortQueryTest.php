<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Sorts;

use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\QueryWizard\Enums\SortDirection;
use Jackardios\QueryWizard\Exceptions\InvalidSortQuery;
use Jackardios\QueryWizard\Sorts\AbstractSort;
use Jackardios\QueryWizard\Values\Sort;

/**
 * @group unit
 * @group sort
 */
class SortQueryTest extends UnitTestCase
{
    /** @test */
    public function it_sorts_ascending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('name')
            ->allowedSorts('name');
        $wizard->build();

        $this->assertEquals([['name' => 'asc']], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_sorts_descending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('-name')
            ->allowedSorts('name');
        $wizard->build();

        $this->assertEquals([['name' => 'desc']], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_sorts_by_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('nickname')
            ->allowedSorts(FieldSort::make('name', 'nickname'));
        $wizard->build();

        $this->assertEquals([['name' => 'asc']], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_sorts_descending_with_alias(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('-nickname')
            ->allowedSorts(FieldSort::make('name', 'nickname'));
        $wizard->build();

        $this->assertEquals([['name' => 'desc']], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_uses_default_sort_when_no_sort_requested(): void
    {
        $wizard = $this
            ->createElasticWizardFromQuery()
            ->allowedSorts('name')
            ->defaultSorts('name');
        $wizard->build();

        $this->assertEquals([['name' => 'asc']], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_uses_default_descending_sort(): void
    {
        $wizard = $this
            ->createElasticWizardFromQuery()
            ->allowedSorts('-name')
            ->defaultSorts('-name');
        $wizard->build();

        $this->assertEquals([['name' => 'desc']], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_ignores_default_when_sort_is_requested(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('id')
            ->allowedSorts('id')
            ->defaultSorts('name');
        $wizard->build();

        $this->assertEquals([['id' => 'asc']], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_sorts_by_multiple_columns(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('name,-id')
            ->allowedSorts('name', 'id');
        $wizard->build();

        $this->assertEquals([
            ['name' => 'asc'],
            ['id' => 'desc'],
        ], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_supports_multiple_default_sorts(): void
    {
        $sortClass = $this->createCustomSort();

        $wizard = $this
            ->createElasticWizardFromQuery()
            ->allowedSorts($sortClass, 'id')
            ->defaultSorts('custom_name', new Sort('id', SortDirection::Descending));
        $wizard->build();

        $this->assertEquals([
            ['name' => 'asc'],
            ['id' => 'desc'],
        ], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_throws_for_invalid_sort(): void
    {
        $this->expectException(InvalidSortQuery::class);

        $this
            ->createElasticWizardWithSorts('name')
            ->allowedSorts('id')
            ->build();
    }

    /** @test */
    public function it_has_no_sorts_when_none_requested(): void
    {
        $wizard = $this
            ->createElasticWizardFromQuery()
            ->allowedSorts('name');
        $wizard->build();

        $this->assertEquals([], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_sorts_by_custom_sort_class(): void
    {
        $sortClass = $this->createCustomSort();

        $wizard = $this
            ->createElasticWizardWithSorts('custom_name')
            ->allowedSorts($sortClass);
        $wizard->build();

        $this->assertEquals([['name' => 'asc']], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_does_not_add_duplicate_sort_clauses(): void
    {
        $wizard = ElasticQueryWizard::for(TestModel::class)
            ->allowedSorts('name')
            ->defaultSorts('name', '-name');
        $wizard->build();

        $this->assertEquals([['name' => 'asc']], $this->getSorts($wizard->getSubject()));
    }

    /** @test */
    public function it_applies_advanced_field_sort_options(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('price_sort')
            ->allowedSorts(
                FieldSort::make('price', 'price_sort')
                    ->missingLast()
                    ->mode('avg')
                    ->unmappedType('long')
                    ->nested(['path' => 'offers'])
                    ->numericType('double')
                    ->format('strict_date_optional_time')
            );
        $wizard->build();

        $this->assertEquals([
            [
                'price' => [
                    'order' => 'asc',
                    'missing' => '_last',
                    'mode' => 'avg',
                    'unmapped_type' => 'long',
                    'nested' => ['path' => 'offers'],
                    'numeric_type' => 'double',
                    'format' => 'strict_date_optional_time',
                ],
            ],
        ], $this->getSorts($wizard->getSubject()));
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
}
