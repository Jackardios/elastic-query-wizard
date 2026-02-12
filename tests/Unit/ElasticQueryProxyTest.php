<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use BadMethodCallException;
use Jackardios\ElasticQueryWizard\ElasticQuery;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 * @group factory
 */
class ElasticQueryProxyTest extends TestCase
{
    /** @test */
    public function it_proxies_query_factory_methods(): void
    {
        $query = ElasticQuery::match('title', 'laravel');

        $this->assertInstanceOf(MatchQuery::class, $query);
        $this->assertSame(
            ['match' => ['title' => ['query' => 'laravel']]],
            $query->toArray()
        );
    }

    /** @test */
    public function it_proxies_bool_factory_method(): void
    {
        $query = ElasticQuery::bool();

        $this->assertInstanceOf(BoolQuery::class, $query);
    }

    /** @test */
    public function it_throws_for_unknown_method(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Method "Jackardios\\ElasticQueryWizard\\ElasticQuery::unknownMethod" does not exist.');

        ElasticQuery::unknownMethod();
    }
}

