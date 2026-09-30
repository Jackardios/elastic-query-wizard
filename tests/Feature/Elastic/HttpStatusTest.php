<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Feature\Elastic;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Route;
use Jackardios\ElasticQueryWizard\ElasticFilter;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\GeoModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\SoftDeleteModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\ElasticQueryWizard\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every structural filter answers a value it can read with a search and a
 * value it cannot read with a 400, before Elasticsearch sees the request.
 */
#[Group('elastic')]
#[Group('filter')]
class HttpStatusTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('scout.soft_delete', true);

        Route::get('/test-models', fn () => $this->ids(ElasticQueryWizard::for(TestModel::class)->allowedFilters(
            ElasticFilter::term('category'),
            ElasticFilter::range('id'),
            ElasticFilter::dateRange('created_at', 'created'),
            ElasticFilter::exists('category', 'has_category'),
            ElasticFilter::null('deleted_at', 'not_deleted'),
            ElasticFilter::match('name', 'text'),
            ElasticFilter::queryString('name', 'q'),
            ElasticFilter::regexp('name.keyword', 'pattern'),
        )));
        Route::get('/soft-delete-models', fn () => $this->ids(ElasticQueryWizard::for(SoftDeleteModel::class)->allowedFilters(
            ElasticFilter::trashed(),
        )));
        Route::get('/geo-models', fn () => $this->ids(ElasticQueryWizard::for(GeoModel::class)->allowedFilters(
            ElasticFilter::geoBoundingBox('location', 'bbox'),
            ElasticFilter::geoDistance('location', 'near'),
            ElasticFilter::geoShape('boundary'),
        )));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function readableRequests(): iterable
    {
        yield 'term list' => ['/test-models?filter[category]=x,y'];
        yield 'range' => ['/test-models?filter[id][gte]=1&filter[id][lt]=100'];
        yield 'date range' => ['/test-models?filter[created][from]=2024-01-01&filter[created][to]=2024-01-31T12:00:00%2B03:00'];
        yield 'exists' => ['/test-models?filter[has_category]=false'];
        yield 'null' => ['/test-models?filter[not_deleted]=true'];
        yield 'match' => ['/test-models?filter[text]=red,%20blue'];
        yield 'query string' => ['/test-models?filter[q]=foo*%20AND%20bar'];
        yield 'regexp' => ['/test-models?filter[pattern]=a.{1,3}'];
        yield 'trashed' => ['/soft-delete-models?filter[trashed]=with'];
        yield 'bounding box' => ['/geo-models?filter[bbox][]=36&filter[bbox][]=55&filter[bbox][]=38&filter[bbox][]=56'];
        yield 'named bounding box' => ['/geo-models?filter[bbox][left]=36&filter[bbox][bottom]=55&filter[bbox][right]=38&filter[bbox][top]=56'];
        yield 'distance' => ['/geo-models?filter[near][lat]=55.75&filter[near][lon]=37.62&filter[near][distance]=10%20km'];
        yield 'envelope' => ['/geo-models?filter[boundary][type]=envelope&filter[boundary][coordinates][0][0]=-10&filter[boundary][coordinates][0][1]=10&filter[boundary][coordinates][1][0]=10&filter[boundary][coordinates][1][1]=-10'];
        yield 'point' => ['/geo-models?filter[boundary][type]=point&filter[boundary][coordinates][0]=37.62&filter[boundary][coordinates][1]=55.75'];
    }

    #[Test]
    #[DataProvider('readableRequests')]
    public function a_readable_value_is_searched(string $uri): void
    {
        $this->getJson($uri)->assertOk();
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadableRequests(): iterable
    {
        yield 'unknown filter' => ['/test-models?filter[unknown]=1', 'unknown'];
        yield 'term nested list' => ['/test-models?filter[category][a][b]=x', 'category'];
        yield 'range scalar' => ['/test-models?filter[id]=5', 'id'];
        yield 'range text bound' => ['/test-models?filter[id][gte]=abc', 'id'];
        yield 'range date math' => ['/test-models?filter[id][gte]=now-1d', 'id'];
        yield 'date range text' => ['/test-models?filter[created][from]=yesterday', 'created'];
        yield 'date range epoch' => ['/test-models?filter[created][from]=1700000000', 'created'];
        yield 'date range scalar' => ['/test-models?filter[created]=2024-01-01', 'created'];
        yield 'exists' => ['/test-models?filter[has_category]=maybe', 'has_category'];
        yield 'null' => ['/test-models?filter[not_deleted]=maybe', 'not_deleted'];
        yield 'match list' => ['/test-models?filter[text][]=red&filter[text][]=blue', 'text'];
        yield 'query string leading wildcard' => ['/test-models?filter[q]=*foo', 'q'];
        yield 'regexp too long' => ['/test-models?filter[pattern]='.str_repeat('a', 1001), 'pattern'];
        yield 'trashed' => ['/soft-delete-models?filter[trashed]=1', 'trashed'];
        yield 'bounding box latitude' => ['/geo-models?filter[bbox][]=36&filter[bbox][]=-100&filter[bbox][]=38&filter[bbox][]=56', 'bbox'];
        yield 'bounding box three numbers' => ['/geo-models?filter[bbox][]=36&filter[bbox][]=55&filter[bbox][]=38', 'bbox'];
        yield 'distance unit' => ['/geo-models?filter[near][lat]=55.75&filter[near][lon]=37.62&filter[near][distance]=10KM', 'near'];
        yield 'distance zero' => ['/geo-models?filter[near][lat]=55.75&filter[near][lon]=37.62&filter[near][distance]=0km', 'near'];
        yield 'shape type' => ['/geo-models?filter[boundary][type]=circle', 'boundary'];
        yield 'shape scalar' => ['/geo-models?filter[boundary]=point', 'boundary'];
        yield 'indexed shape without indexedShapes()' => ['/geo-models?filter[boundary][type]=indexed_shape&filter[boundary][id]=1', 'boundary'];
        yield 'shape infinite coordinate' => ['/geo-models?filter[boundary][type]=point&filter[boundary][coordinates][0]=1e999&filter[boundary][coordinates][1]=0', 'boundary'];
    }

    #[Test]
    #[DataProvider('unreadableRequests')]
    public function an_unreadable_value_is_a_400_naming_the_filter(string $uri, string $filter): void
    {
        $response = $this->getJson($uri);

        $response->assertStatus(400);
        $this->assertStringContainsString("`{$filter}`", (string) $response->json('message'));
    }

    /**
     * @return list<int>
     */
    private function ids(ElasticQueryWizard $wizard): array
    {
        return $wizard->build()->execute()->models()->pluck('id')->all();
    }
}
