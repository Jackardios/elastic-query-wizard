<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Sorts;

use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use PHPUnit\Framework\TestCase;

/**
 * @group unit
 * @group sort
 */
class FieldSortTest extends TestCase
{
    /** @test */
    public function get_type_returns_field(): void
    {
        $sort = FieldSort::make('name');

        $this->assertEquals('field', $sort->getType());
    }

    /** @test */
    public function get_property_returns_property(): void
    {
        $sort = FieldSort::make('name');

        $this->assertEquals('name', $sort->getProperty());
    }

    /** @test */
    public function get_name_returns_alias_when_set(): void
    {
        $sort = FieldSort::make('name', 'nickname');

        $this->assertEquals('nickname', $sort->getName());
    }

    /** @test */
    public function get_name_returns_property_when_no_alias(): void
    {
        $sort = FieldSort::make('name');

        $this->assertEquals('name', $sort->getName());
    }
}
