<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Jackardios\QueryWizard\Exceptions\InvalidFieldQuery;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;

/**
 * @group elastic
 * @group fields
 * @group elastic-fields
 */
class FieldsTest extends TestCase
{
    protected TestModel $model;

    protected function setUp(): void
    {
        parent::setUp();

        $this->model = TestModel::factory()->create();
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested(): void
    {
        $model = $this
            ->createElasticWizardWithFields([])
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $expectedModel = TestModel::query()->first();

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_fetches_all_columns_if_no_field_was_requested_but_allowed_fields_were_specified(): void
    {
        $model = $this
            ->createElasticWizardWithFields([])
            ->allowedFields('id', 'name')
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $expectedModel = TestModel::query()->first();

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_replaces_selected_columns_on_the_query(): void
    {
        $model = $this
            ->createElasticWizardWithFields(['testModel' => 'name,id'])
            ->addEloquentQueryCallback(function(Builder $query) {
                $query->select(['id', 'is_visible']);
            })
            ->allowedFields(['name', 'id'])
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $expectedModel = TestModel::query()
            ->select("name", "id")
            ->first();

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_can_fetch_specific_columns(): void
    {
        $model = $this
            ->createElasticWizardWithFields(['testModel' => 'name,id'])
            ->allowedFields(['name', 'id'])
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $expectedModel = TestModel::query()
            ->select("name", "id")
            ->first();

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_wont_fetch_a_specific_column_if_its_not_allowed(): void
    {
        $model = $this
            ->createElasticWizardWithFields(['testModel' => 'random-column'])
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $expectedModel = TestModel::query()->first();

        $this->assertModelsAttributesEqual($model, $expectedModel);
    }

    /** @test */
    public function it_guards_against_not_allowed_fields(): void
    {
        $this->expectException(InvalidFieldQuery::class);

        $this
            ->createElasticWizardWithFields(['testModel' => 'random-column'])
            ->allowedFields('name')
            ->build();
    }

    /** @test */
    public function it_wont_use_sketchy_field_requests(): void
    {
        DB::enableQueryLog();

        $this->createElasticWizardWithFields(['testModel' => 'id->"\')from test_models--injection'])
            ->build()
            ->size(1)
            ->execute()
            ->models();

        $this->assertQueryLogDoesntContain('--injection');
    }
}
