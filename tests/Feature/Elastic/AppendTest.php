<?php

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Jackardios\ElasticQueryWizard\Tests\TestCase;
use Illuminate\Contracts\Pagination\CursorPaginator;
use Illuminate\Contracts\Pagination\Paginator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Jackardios\QueryWizard\Exceptions\InvalidAppendQuery;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\AppendModel;

/**
 * @group elastic
 * @group append
 * @group elastic-append
 */
class AppendTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        factory(AppendModel::class, 5)->create();
    }

    /** @test */
    public function it_does_not_require_appends(): void
    {
        $result = $this->createElasticWizardFromQuery([], AppendModel::class)
            ->setAllowedAppends('fullname')
            ->build()
            ->execute();

        $this->assertEquals(AppendModel::count(), $result->total());
    }

    /** @test */
    public function it_can_append_attributes(): void
    {
        $model = $this
            ->createElasticWizardWithAppends('fullname')
            ->setAllowedAppends('fullname')
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
            ->setAllowedAppends('fullname')
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
            ->setAllowedAppends('FullName')
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
            ->setAllowedAppends('FullName')
            ->build()
            ->paginate()
            ->onlyModels();

        $this->assertPaginateAttributeLoaded($models, 'FullName');
    }

    /** @test */
    public function it_guards_against_invalid_appends(): void
    {
        $this->expectException(InvalidAppendQuery::class);

        $this
            ->createElasticWizardWithAppends('random-attribute-to-append')
            ->setAllowedAppends('attribute-to-append')
            ->build();
    }

    /** @test */
    public function it_can_allow_multiple_appends(): void
    {
        $model = $this
            ->createElasticWizardWithAppends('fullname')
            ->setAllowedAppends('fullname', 'randomAttribute')
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
            ->setAllowedAppends(['fullname', 'randomAttribute'])
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
            ->setAllowedAppends(['fullname', 'reversename'])
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
}
