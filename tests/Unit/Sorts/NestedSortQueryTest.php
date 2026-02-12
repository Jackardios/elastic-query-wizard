<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Sorts;

use Jackardios\ElasticQueryWizard\Sorts\NestedSort;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Support\Query;

/**
 * @group unit
 * @group sort
 */
class NestedSortQueryTest extends UnitTestCase
{
    /** @test */
    public function it_sorts_by_nested_field_ascending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('price')
            ->allowedSorts(NestedSort::make('variants', 'price', 'price'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'variants.price' => [
                    'order' => 'asc',
                    'nested' => ['path' => 'variants'],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_sorts_by_nested_field_descending(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('-price')
            ->allowedSorts(NestedSort::make('variants', 'price', 'price'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'variants.price' => [
                    'order' => 'desc',
                    'nested' => ['path' => 'variants'],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_applies_mode(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('lowest_price')
            ->allowedSorts(
                NestedSort::make('variants', 'price', 'lowest_price')->mode('min')
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'variants.price' => [
                    'order' => 'asc',
                    'mode' => 'min',
                    'nested' => ['path' => 'variants'],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_applies_missing_first(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('price')
            ->allowedSorts(
                NestedSort::make('variants', 'price', 'price')->missingFirst()
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'variants.price' => [
                    'order' => 'asc',
                    'missing' => '_first',
                    'nested' => ['path' => 'variants'],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_applies_missing_last(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('price')
            ->allowedSorts(
                NestedSort::make('variants', 'price', 'price')->missingLast()
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'variants.price' => [
                    'order' => 'asc',
                    'missing' => '_last',
                    'nested' => ['path' => 'variants'],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_applies_unmapped_type(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('price')
            ->allowedSorts(
                NestedSort::make('variants', 'price', 'price')->unmappedType('long')
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'variants.price' => [
                    'order' => 'asc',
                    'unmapped_type' => 'long',
                    'nested' => ['path' => 'variants'],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_applies_nested_filter_with_query_interface(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('price')
            ->allowedSorts(
                NestedSort::make('offers', 'price', 'price')
                    ->nestedFilter(Query::term('offers.active', true))
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'offers.price' => [
                    'order' => 'asc',
                    'nested' => [
                        'path' => 'offers',
                        'filter' => [
                            'term' => ['offers.active' => ['value' => true]],
                        ],
                    ],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_applies_nested_filter_with_closure(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('price')
            ->allowedSorts(
                NestedSort::make('offers', 'price', 'price')
                    ->nestedFilter(fn() => Query::term('offers.active', true))
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'offers.price' => [
                    'order' => 'asc',
                    'nested' => [
                        'path' => 'offers',
                        'filter' => [
                            'term' => ['offers.active' => ['value' => true]],
                        ],
                    ],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_applies_nested_filter_with_array(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('price')
            ->allowedSorts(
                NestedSort::make('offers', 'price', 'price')
                    ->nestedFilter(['term' => ['offers.active' => ['value' => true]]])
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'offers.price' => [
                    'order' => 'asc',
                    'nested' => [
                        'path' => 'offers',
                        'filter' => [
                            'term' => ['offers.active' => ['value' => true]],
                        ],
                    ],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_applies_max_children(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('price')
            ->allowedSorts(
                NestedSort::make('variants', 'price', 'price')->maxChildren(10)
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'variants.price' => [
                    'order' => 'asc',
                    'nested' => [
                        'path' => 'variants',
                        'max_children' => 10,
                    ],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_combines_all_options(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('best_price')
            ->allowedSorts(
                NestedSort::make('offers', 'price', 'best_price')
                    ->mode('min')
                    ->missingLast()
                    ->unmappedType('float')
                    ->nestedFilter(Query::term('offers.active', true))
                    ->maxChildren(5)
            );
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'offers.price' => [
                    'order' => 'asc',
                    'missing' => '_last',
                    'mode' => 'min',
                    'unmapped_type' => 'float',
                    'nested' => [
                        'path' => 'offers',
                        'filter' => [
                            'term' => ['offers.active' => ['value' => true]],
                        ],
                        'max_children' => 5,
                    ],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_uses_alias_correctly(): void
    {
        $wizard = $this
            ->createElasticWizardWithSorts('lowest')
            ->allowedSorts(NestedSort::make('variants', 'price', 'price', 'lowest'));
        $wizard->build();

        $sorts = $this->getSorts($wizard->getSubject());

        $this->assertEquals([
            [
                'variants.price' => [
                    'order' => 'asc',
                    'nested' => ['path' => 'variants'],
                ],
            ],
        ], $sorts);
    }

    /** @test */
    public function it_returns_correct_type(): void
    {
        $sort = NestedSort::make('variants', 'price', 'price');

        $this->assertEquals('nested', $sort->getType());
    }
}
