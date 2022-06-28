<?php

namespace Jackardios\ElasticQueryWizard\Tests\Concerns;

use Illuminate\Http\Request;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\AppendModel;
use Jackardios\ElasticQueryWizard\Tests\Fixtures\Models\TestModel;
use Jackardios\QueryWizard\QueryParametersManager;

trait QueryWizardTestingHelpers
{
    protected function createElasticWizardFromQuery(array $query = [], $subject = null): ElasticQueryWizard
    {
        return ElasticQueryWizard::for($subject ?? TestModel::class, new QueryParametersManager(new Request($query)));
    }

    protected function createElasticWizardWithAppends(string|array $appends, $subject = null): ElasticQueryWizard
    {
        return $this->createElasticWizardFromQuery([
            'append' => $appends,
        ],  $subject ?? AppendModel::class);
    }

    protected function createElasticWizardWithIncludes(array|string $includes, $subject = null): ElasticQueryWizard
    {
        return $this->createElasticWizardFromQuery([
            'include' => $includes,
        ], $subject ?? null);
    }

    protected function createElasticWizardWithFields(array|string $fields, $subject = null): ElasticQueryWizard
    {
        return $this->createElasticWizardFromQuery([
            'fields' => $fields,
        ], $subject ?? null);
    }

    protected function createElasticWizardWithSorts(array|string $sorts, $subject = null): ElasticQueryWizard
    {
        return $this->createElasticWizardFromQuery([
            'sort' => $sorts,
        ], $subject ?? null);
    }

    protected function createElasticWizardWithFilters(array $filters, $subject = null): ElasticQueryWizard
    {
        return $this->createElasticWizardFromQuery([
            'filter' => $filters,
        ], $subject ?? null);
    }
}
