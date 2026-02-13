<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Filters;

use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use Jackardios\EsScoutDriver\Query\QueryInterface;

/**
 * @group unit
 * @group filter
 */
class RawQueryFilterTest extends UnitTestCase
{
    /** @test */
    public function it_applies_raw_query_from_custom_filter_at_root_level(): void
    {
        $rawFilter = new class ('status', 'status') extends AbstractElasticFilter {
            public function __construct(string $property, ?string $alias = null)
            {
                parent::__construct($property, $alias);
            }

            public function getType(): string
            {
                return 'raw_test';
            }

            public function buildQuery(mixed $value): QueryInterface|array|null
            {
                if ($value === null || $value === '') {
                    return null;
                }

                return ['term' => [$this->getProperty() => ['value' => $value]]];
            }
        };

        $wizard = $this
            ->createElasticWizardWithFilters([
                'status' => 'active',
            ])
            ->allowedFilters([$rawFilter]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertEquals(['term' => ['status' => ['value' => 'active']]], $filterQueries[0]);
    }

    /** @test */
    public function it_applies_raw_query_from_custom_filter_inside_group(): void
    {
        $rawFilter = new class ('status', 'status') extends AbstractElasticFilter {
            public function __construct(string $property, ?string $alias = null)
            {
                parent::__construct($property, $alias);
            }

            public function getType(): string
            {
                return 'raw_test';
            }

            public function buildQuery(mixed $value): QueryInterface|array|null
            {
                if ($value === null || $value === '') {
                    return null;
                }

                return ['term' => [$this->getProperty() => ['value' => $value]]];
            }
        };

        $wizard = $this
            ->createElasticWizardWithFilters([
                'status' => 'active',
            ])
            ->allowedFilters([
                ElasticGroup::bool('advanced')
                    ->children([$rawFilter]),
            ]);

        $wizard->build();

        $filterQueries = $this->getFilterQueries($wizard->boolQuery());

        $this->assertCount(1, $filterQueries);
        $this->assertArrayHasKey('bool', $filterQueries[0]);
        $this->assertArrayHasKey('filter', $filterQueries[0]['bool']);
        $this->assertCount(1, $filterQueries[0]['bool']['filter']);
        $this->assertEquals(
            ['term' => ['status' => ['value' => 'active']]],
            $filterQueries[0]['bool']['filter'][0]
        );
    }
}
