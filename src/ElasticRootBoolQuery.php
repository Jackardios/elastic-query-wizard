<?php

namespace Jackardios\ElasticQueryWizard;

use Closure;
use Elastic\ScoutDriverPlus\Builders\AbstractParameterizedQueryBuilder;
use Elastic\ScoutDriverPlus\Builders\QueryBuilderInterface;
use Elastic\ScoutDriverPlus\QueryParameters\ParameterCollection;
use Elastic\ScoutDriverPlus\QueryParameters\Shared\MinimumShouldMatchParameter;
use Elastic\ScoutDriverPlus\QueryParameters\Transformers\FlatArrayTransformer;
use Elastic\ScoutDriverPlus\QueryParameters\Validators\OneOfValidator;
use Elastic\ScoutDriverPlus\Support\Arr;
use Elastic\ScoutDriverPlus\Support\Query;
use Illuminate\Support\Collection;

class ElasticRootBoolQuery extends AbstractParameterizedQueryBuilder
{
    use MinimumShouldMatchParameter;

    protected string $type = 'bool';
    private ?int $softDeleted = 0;

    protected Collection $mustQueries;
    protected Collection $mustNotQueries;
    protected Collection $shouldQueries;
    protected Collection $filterQueries;

    public function __construct()
    {
        $this->parameters = new ParameterCollection();
        $this->parameterValidator = new OneOfValidator(['must', 'must_not', 'should', 'filter']);
        $this->parameterTransformer = new FlatArrayTransformer();

        $this->mustQueries = collect([]);
        $this->mustNotQueries = collect([]);
        $this->shouldQueries = collect([]);
        $this->filterQueries = collect([]);
    }

    public function withTrashed(): self
    {
        $this->softDeleted = null;
        return $this;
    }

    public function onlyTrashed(): self
    {
        $this->softDeleted = 1;
        return $this;
    }

    /**
     * @param Closure|QueryBuilderInterface|array $query
     * @param string|int|null $key
     *
     * @return QueryBuilderInterface|array|null
     */
    public function must($query, $key = null)
    {
        if ($key === null || ! $this->mustQueries->has($key)) {
            $this->mustQueries->put($key, value($query));
        }

        return $this->mustQueries->get($key);
    }

    /**
     * @param Closure|QueryBuilderInterface|array $query
     * @param string|int|null $key
     *
     * @return QueryBuilderInterface|array|null
     */
    public function mustNot($query, $key = null)
    {
        if ($key === null || ! $this->mustNotQueries->has($key)) {
            $this->mustNotQueries->put($key, value($query));
        }

        return $this->mustNotQueries->get($key);
    }

    /**
     * @param Closure|QueryBuilderInterface|array $query
     * @param string|int|null $key
     *
     * @return QueryBuilderInterface|array|null
     */
    public function should($query, $key = null)
    {
        if ($key === null || ! $this->shouldQueries->has($key)) {
            $this->shouldQueries->put($key, value($query));
        }

        return $this->shouldQueries->get($key);
    }

    /**
     * @param Closure|QueryBuilderInterface|array $query
     * @param string|int|null $key
     *
     * @return QueryBuilderInterface|array|null
     */
    public function filter($query, $key = null)
    {
        if ($key === null || ! $this->filterQueries->has($key)) {
            $this->filterQueries->put($key, value($query));
        }

        return $this->filterQueries->get($key);
    }

    public function buildQuery(): array
    {
        $this->buildParameters();

        $query = parent::buildQuery();

        if (isset($this->softDeleted) && config('scout.soft_delete', false)) {
            $query[$this->type]['filter'] = isset($query[$this->type]['filter'])
                ? Arr::wrapAssoc($query[$this->type]['filter'])
                : [];

            $query[$this->type]['filter'][] = [
                'term' => [
                    '__soft_deleted' => $this->softDeleted,
                ],
            ];
        }

        return $query;
    }

    protected function buildParameters(): void
    {
        if ($this->mustQueries->isEmpty()) {
            $this->mustQueries->push(Query::matchAll());
        }

        $buildQuery = static fn ($query) => $query instanceof QueryBuilderInterface ? $query->buildQuery() : $query;

        $this->parameters->put('must', $this->mustQueries->map($buildQuery)->values());
        $this->parameters->put('mustNot', $this->mustNotQueries->map($buildQuery)->values());
        $this->parameters->put('should', $this->shouldQueries->map($buildQuery)->values());
        $this->parameters->put('filter', $this->filterQueries->map($buildQuery)->values());
    }
}
