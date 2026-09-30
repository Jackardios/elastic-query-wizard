<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use BadMethodCallException;
use Jackardios\ElasticQueryWizard\ElasticQuery;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Query\FullText\MatchQuery;
use Jackardios\EsScoutDriver\Support\Query;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('factory')]
class ElasticQueryProxyTest extends TestCase
{
    #[Test]
    public function it_proxies_query_factory_methods(): void
    {
        $query = ElasticQuery::match('title', 'laravel');

        $this->assertInstanceOf(MatchQuery::class, $query);
        $this->assertSame(
            ['match' => ['title' => ['query' => 'laravel']]],
            $query->toArray()
        );
    }

    #[Test]
    public function it_proxies_bool_factory_method(): void
    {
        $query = ElasticQuery::bool();

        $this->assertInstanceOf(BoolQuery::class, $query);
    }

    #[Test]
    public function it_proxies_macros(): void
    {
        Query::macro('proxyTestMacro', static fn (string $field) => Query::exists($field));

        try {
            $this->assertSame(['exists' => ['field' => 'title']], ElasticQuery::proxyTestMacro('title')->toArray());
        } finally {
            Query::flushMacros();
        }
    }

    #[Test]
    public function it_throws_for_unknown_method(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->expectExceptionMessage('Call to undefined method Jackardios\\ElasticQueryWizard\\ElasticQuery::unknownMethod()');

        ElasticQuery::unknownMethod();
    }
}
