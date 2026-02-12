<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit;

use Jackardios\ElasticQueryWizard\ElasticInclude;
use Jackardios\QueryWizard\Eloquent\Includes\CountInclude;
use Jackardios\QueryWizard\Eloquent\Includes\ExistsInclude;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\Includes\CallbackInclude;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 * @group factory
 */
class ElasticIncludeFactoryTest extends TestCase
{
    /** @test */
    public function relationship_creates_relationship_include(): void
    {
        $include = ElasticInclude::relationship('relation', 'alias');

        $this->assertInstanceOf(RelationshipInclude::class, $include);
        $this->assertEquals('relation', $include->getRelation());
        $this->assertEquals('alias', $include->getName());
    }

    /** @test */
    public function count_creates_count_include(): void
    {
        $include = ElasticInclude::count('relation', 'alias');

        $this->assertInstanceOf(CountInclude::class, $include);
        $this->assertEquals('relation', $include->getRelation());
        $this->assertEquals('alias', $include->getName());
    }

    /** @test */
    public function callback_creates_callback_include(): void
    {
        $callback = fn($subject) => $subject;
        $include = ElasticInclude::callback('name', $callback, 'alias');

        $this->assertInstanceOf(CallbackInclude::class, $include);
        $this->assertEquals('name', $include->getRelation());
        $this->assertEquals('alias', $include->getName());
    }

    /** @test */
    public function relationship_without_alias_uses_relation_as_name(): void
    {
        $include = ElasticInclude::relationship('relatedModels');

        $this->assertInstanceOf(RelationshipInclude::class, $include);
        $this->assertEquals('relatedModels', $include->getRelation());
        $this->assertEquals('relatedModels', $include->getName());
    }

    /** @test */
    public function count_without_alias_uses_relation_as_name(): void
    {
        $include = ElasticInclude::count('relatedModels');

        $this->assertInstanceOf(CountInclude::class, $include);
        $this->assertEquals('relatedModels', $include->getRelation());
        $this->assertEquals('relatedModels', $include->getName());
    }

    /** @test */
    public function exists_creates_exists_include(): void
    {
        $include = ElasticInclude::exists('relation', 'alias');

        $this->assertInstanceOf(ExistsInclude::class, $include);
        $this->assertEquals('relation', $include->getRelation());
        $this->assertEquals('alias', $include->getName());
    }

    /** @test */
    public function exists_without_alias_uses_relation_as_name(): void
    {
        $include = ElasticInclude::exists('relatedModels');

        $this->assertInstanceOf(ExistsInclude::class, $include);
        $this->assertEquals('relatedModels', $include->getRelation());
        $this->assertEquals('relatedModels', $include->getName());
    }
}
