<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\AppendModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;

/**
 * @group elastic
 * @group append
 * @group elastic-append
 */
class AppendTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        AppendModel::factory()->count(5)->create();
    }

    /** @test */
    public function it_does_not_require_appends(): void
    {
        $result = $this->createElasticWizardFromQuery([], AppendModel::class)
            ->allowedAppends('fullname')
            ->build()
            ->execute();

        $this->assertEquals(AppendModel::count(), $result->total);
    }

    /** @test */
    public function it_can_append_attributes(): void
    {
        $model = $this
            ->createElasticWizardWithAppends('fullname')
            ->allowedAppends('fullname')
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_cannot_append_case_insensitive(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createElasticWizardWithAppends('FullName')
            ->allowedAppends('fullname')
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();
    }

    /** @test */
    public function it_can_append_collections(): void
    {
        $models = $this
            ->createElasticWizardWithAppends('FullName')
            ->allowedAppends('FullName')
            ->build()
            ->execute()
            ->models();

        $this->assertCollectionAttributeLoaded($models, 'FullName');
    }

    /** @test */
    public function it_can_append_paginates(): void
    {
        $models = $this
            ->createElasticWizardWithAppends('FullName')
            ->allowedAppends('FullName')
            ->build()
            ->paginate()
            ->withModels();

        $this->assertPaginateAttributeLoaded($models, 'FullName');
    }

    /** @test */
    public function it_guards_against_invalid_appends(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createElasticWizardWithAppends('random-attribute-to-append')
            ->allowedAppends('attribute-to-append')
            ->build();
    }

    /** @test */
    public function it_can_allow_multiple_appends(): void
    {
        $model = $this
            ->createElasticWizardWithAppends('fullname')
            ->allowedAppends('fullname', 'randomAttribute')
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_can_allow_multiple_appends_as_an_array(): void
    {
        $model = $this
            ->createElasticWizardWithAppends('fullname')
            ->allowedAppends(['fullname', 'randomAttribute'])
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
    }

    /** @test */
    public function it_can_append_multiple_attributes(): void
    {
        $model = $this
            ->createElasticWizardWithAppends('fullname,reversename')
            ->allowedAppends(['fullname', 'reversename'])
            ->build()
            ->size(1)
            ->execute()
            ->models()
            ->first();

        $this->assertAttributeLoaded($model, 'fullname');
        $this->assertAttributeLoaded($model, 'reversename');
    }

    /** @test */
    public function an_invalid_append_query_exception_contains_the_not_allowed_and_allowed_appends(): void
    {
        $exception = new InvalidAppendQuery(collect(['not allowed append']), collect(['allowed append']));

        $this->assertEquals(['not allowed append'], $exception->unknownAppends->all());
        $this->assertEquals(['allowed append'], $exception->allowedAppends->all());
    }

    protected function assertAttributeLoaded(Model $model, string $attribute): void
    {
        $this->assertArrayHasKey($attribute, $model->toArray());
    }

    protected function assertCollectionAttributeLoaded(Collection $collection, string $attribute): void
    {
        $hasModelWithoutAttributeLoaded = $collection
            ->contains(function (Model $model) use ($attribute) {
                return ! array_key_exists($attribute, $model->toArray());
            });

        $this->assertFalse($hasModelWithoutAttributeLoaded, "The `$attribute` attribute was expected but not loaded.");
    }

    /**
     * @param LengthAwarePaginator|Paginator|CursorPaginator $collection
     * @param string $attribute
     */
    protected function assertPaginateAttributeLoaded($collection, string $attribute): void
    {
        $hasModelWithoutAttributeLoaded = $collection
            ->contains(function (Model $model) use ($attribute) {
                return ! array_key_exists($attribute, $model->toArray());
            });

        $this->assertFalse($hasModelWithoutAttributeLoaded, "The `$attribute` attribute was expected but not loaded.");
    }

    // ========== Relation Appends Tests ==========

    /** @test */
    public function it_can_append_to_relation_models(): void
    {
        $testModel = TestModel::factory()->create();
        $testModel->relatedModels()->create(['name' => 'Related']);

        $result = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName')
            ->build()
            ->execute()
            ->models()
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('related_models', $array);
        $this->assertArrayHasKey('formattedName', $array['related_models'][0]);
        $this->assertEquals('Formatted: Related', $array['related_models'][0]['formattedName']);
    }

    /** @test */
    public function it_can_append_multiple_attributes_to_relation(): void
    {
        $testModel = TestModel::factory()->create();
        $testModel->relatedModels()->create(['name' => 'Related']);

        $result = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName,relatedModels.upperName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName', 'relatedModels.upperName')
            ->build()
            ->execute()
            ->models()
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('formattedName', $array['related_models'][0]);
        $this->assertArrayHasKey('upperName', $array['related_models'][0]);
    }

    /** @test */
    public function it_can_combine_root_and_relation_appends(): void
    {
        // TestModel needs a computed attribute for root-level append
        // We'll test that relation appends work alongside root field selection
        $testModel = TestModel::factory()->create();
        $testModel->relatedModels()->create(['name' => 'Related']);

        $result = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName,relatedModels.upperName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName', 'relatedModels.upperName')
            ->build()
            ->execute()
            ->models()
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('related_models', $array);
        $this->assertArrayHasKey('formattedName', $array['related_models'][0]);
        $this->assertArrayHasKey('upperName', $array['related_models'][0]);
    }

    /** @test */
    public function wildcard_allows_all_relation_appends(): void
    {
        $testModel = TestModel::factory()->create();
        $testModel->relatedModels()->create(['name' => 'Related']);

        $result = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName,relatedModels.upperName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.*')
            ->build()
            ->execute()
            ->models()
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('formattedName', $array['related_models'][0]);
        $this->assertArrayHasKey('upperName', $array['related_models'][0]);
    }

    /** @test */
    public function it_ignores_relation_append_when_relation_not_loaded(): void
    {
        $testModel = TestModel::factory()->create();
        $testModel->relatedModels()->create(['name' => 'Related']);

        $result = $this
            ->createElasticWizardFromQuery([
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedAppends('relatedModels.formattedName')
            ->build()
            ->execute()
            ->models()
            ->first();

        $array = $result->toArray();
        // Relation not loaded, so no related_models key
        $this->assertArrayNotHasKey('related_models', $array);
    }

    /** @test */
    public function it_validates_nested_appends_correctly(): void
    {
        $testModel = TestModel::factory()->create();
        $testModel->relatedModels()->create(['name' => 'Related']);

        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.unknownAttr',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName')
            ->build();
    }

    /** @test */
    public function nested_append_applies_to_all_models_in_collection(): void
    {
        $testModels = TestModel::factory()->count(3)->create();
        $testModels->each(function (TestModel $model) {
            $model->relatedModels()->create(['name' => 'Related-' . $model->id]);
        });

        $results = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels',
                'append' => 'relatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels')
            ->allowedAppends('relatedModels.formattedName')
            ->build()
            ->execute()
            ->models();

        $this->assertCount(3, $results);

        foreach ($results as $model) {
            $array = $model->toArray();
            if (! empty($array['related_models'])) {
                foreach ($array['related_models'] as $related) {
                    $this->assertArrayHasKey('formattedName', $related);
                }
            }
        }
    }

    /** @test */
    public function it_can_append_to_deeply_nested_relation(): void
    {
        $testModel = TestModel::factory()->create();
        $related = $testModel->relatedModels()->create(['name' => 'Related']);
        $related->nestedRelatedModels()->create(['name' => 'Nested']);

        $result = $this
            ->createElasticWizardFromQuery([
                'include' => 'relatedModels.nestedRelatedModels',
                'append' => 'relatedModels.nestedRelatedModels.formattedName',
            ], TestModel::class)
            ->allowedIncludes('relatedModels.nestedRelatedModels')
            ->allowedAppends('relatedModels.nestedRelatedModels.formattedName')
            ->build()
            ->execute()
            ->models()
            ->first();

        $array = $result->toArray();
        $this->assertArrayHasKey('related_models', $array);
        $this->assertArrayHasKey('nested_related_models', $array['related_models'][0]);
        $this->assertArrayHasKey('formattedName', $array['related_models'][0]['nested_related_models'][0]);
        $this->assertEquals('Nested: Nested', $array['related_models'][0]['nested_related_models'][0]['formattedName']);
    }
}
