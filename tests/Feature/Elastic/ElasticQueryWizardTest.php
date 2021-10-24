<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use ElasticScoutDriverPlus\Support\Query;
use Illuminate\Support\Facades\Config;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\SoftDeleteModel;

/**
 * @group elastic
 * @group wizard
 * @group elastic-wizard
 */
class ElasticQueryWizardTest extends TestCase
{
    /** @test */
    public function it_can_not_be_given_a_string_that_is_not_a_class_name(): void
    {
        $this->expectException(InvalidSubject::class);

        $this->expectExceptionMessage('$subject must be a model that uses `ElasticScoutDriverPlus\Searchable` trait');

        ElasticQueryWizard::for('not a class name');
    }

    /** @test */
    public function it_can_not_be_given_an_object_that_is_neither_relation_nor_eloquent_builder(): void
    {
        $this->expectException(InvalidSubject::class);

        $this->expectExceptionMessage('$subject must be a model that uses `ElasticScoutDriverPlus\Searchable` trait');

        ElasticQueryWizard::for($this);
    }

    /** @test */
    public function it_can_query_soft_deletes(): void
    {
        Config::set('scout.soft_delete', true);

        $queryWizard = ElasticQueryWizard::for(SoftDeleteModel::class);

        $defaultQuery = Query::bool()->must(Query::matchAll());
        $withTrashedQuery = Query::bool()->must(Query::matchAll())->withTrashed();

        $models = factory(SoftDeleteModel::class, 5)->create();

        $this->assertCount(5, $queryWizard->setQuery($defaultQuery)->execute()->models());

        $models[0]->delete();

        $this->assertCount(4, $queryWizard->setQuery($defaultQuery)->execute()->models());
        $this->assertCount(5, $queryWizard->setQuery($withTrashedQuery)->execute()->models());
    }
}
