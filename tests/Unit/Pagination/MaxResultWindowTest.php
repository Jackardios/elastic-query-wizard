<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Pagination;

use Jackardios\ElasticQueryWizard\Exceptions\MaxResultWindowExceeded;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * A page that ends past the result window is a 400 before Elasticsearch refuses it with a 500.
 */
#[Group('unit')]
#[Group('pagination')]
class MaxResultWindowTest extends UnitTestCase
{
    #[Test]
    public function a_page_past_the_default_window_is_refused(): void
    {
        try {
            $this->createElasticWizardFromQuery()->paginate(10, 'page', 1001);
            $this->fail('Expected MaxResultWindowExceeded.');
        } catch (MaxResultWindowExceeded $exception) {
            $this->assertSame(400, $exception->getStatusCode());
            $this->assertSame('max_result_window_exceeded', $exception->errorCode);
            $this->assertSame(MaxResultWindowExceeded::ERROR_CODE, $exception->errorCode);
            $this->assertSame('page', $exception->parameter);
            $this->assertSame([1001, 10, 10000], [$exception->page, $exception->perPage, $exception->maxResultWindow]);
        }
    }

    #[Test]
    public function the_page_is_read_from_the_request(): void
    {
        $this->app['request']->query->set('p', '5');

        $this->expectException(MaxResultWindowExceeded::class);

        $this->createElasticWizardFromQuery()->paginate(3000, 'p');
    }

    #[Test]
    public function the_window_is_configurable(): void
    {
        config()->set('elastic-query-wizard.max_result_window', 100);

        $this->expectException(MaxResultWindowExceeded::class);
        $this->expectExceptionMessage('Page 11 of 10 results ends past the first 100 results');

        $this->createElasticWizardFromQuery()->paginate(10, 'page', 11);
    }

    #[Test]
    public function an_invalid_window_is_refused(): void
    {
        config()->set('elastic-query-wizard.max_result_window', 0);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('elastic-query-wizard.max_result_window');

        $this->createElasticWizardFromQuery()->paginate(10, 'page', 1);
    }
}
