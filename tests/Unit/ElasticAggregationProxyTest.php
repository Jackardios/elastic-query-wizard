<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use BadMethodCallException;
use Jackardios\ElasticQueryWizard\ElasticAggregation;
use Jackardios\EsScoutDriver\Aggregations\Bucket\TermsAggregation;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('factory')]
class ElasticAggregationProxyTest extends TestCase
{
    #[Test]
    public function it_proxies_aggregation_factory_methods(): void
    {
        $aggregation = ElasticAggregation::terms('category');

        $this->assertInstanceOf(TermsAggregation::class, $aggregation);
        $this->assertSame(
            ['terms' => ['field' => 'category']],
            $aggregation->toArray()
        );
    }

    #[Test]
    public function it_throws_for_unknown_method(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Call to undefined method Jackardios\\ElasticQueryWizard\\ElasticAggregation::unknownMethod()');

        ElasticAggregation::unknownMethod();
    }
}
