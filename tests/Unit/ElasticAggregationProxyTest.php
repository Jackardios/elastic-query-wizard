<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use BadMethodCallException;
use Jackardios\ElasticQueryWizard\ElasticAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\TermsAggregation;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 * @group factory
 */
class ElasticAggregationProxyTest extends TestCase
{
    /** @test */
    public function it_proxies_aggregation_factory_methods(): void
    {
        $aggregation = ElasticAggregation::terms('category');

        $this->assertInstanceOf(TermsAggregation::class, $aggregation);
        $this->assertSame(
            ['terms' => ['field' => 'category']],
            $aggregation->toArray()
        );
    }

    /** @test */
    public function it_throws_for_unknown_method(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method "Jackardios\\ElasticQueryWizard\\ElasticAggregation::unknownMethod" does not exist.');

        ElasticAggregation::unknownMethod();
    }
}
