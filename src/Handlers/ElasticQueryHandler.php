<?php

namespace Jackardios\ElasticQueryWizard\Handlers;

use ElasticAdapter\Search\SearchResponse;
use ElasticScoutDriverPlus\Builders\BoolQueryBuilder;
use ElasticScoutDriverPlus\Builders\QueryBuilderInterface;
use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use ElasticScoutDriverPlus\Support\Query;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Jackardios\ElasticQueryWizard\Handlers\Includes\AbstractElasticInclude;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\AbstractEloquentFilter;
use Jackardios\QueryWizard\Values\Sort;
use Jackardios\ElasticQueryWizard\Handlers\Filters\AbstractElasticFilter;
use Jackardios\ElasticQueryWizard\Handlers\Sorts\AbstractElasticSort;
use Jackardios\ElasticQueryWizard\ElasticQueryWizard;

/**
 * @property ElasticQueryWizard $wizard
 * @property SearchRequestBuilder $subject
 * @method ElasticQueryWizard getWizard()
 * @method SearchRequestBuilder getSubject()
 */
class ElasticQueryHandler extends AbstractQueryHandler
{
    protected static array $baseFilterHandlerClasses = [AbstractEloquentFilter::class, AbstractElasticFilter::class];
    protected static array $baseIncludeHandlerClasses = [AbstractElasticInclude::class];
    protected static array $baseSortHandlerClasses = [AbstractElasticSort::class];

    /** @var callable[] */
    protected array $eloquentQueryCallbacks = [];

    /** @var callable[] */
    protected array $modelQueryCallbacks = [];

    protected BoolQueryBuilder $mainBoolQuery;
    protected Collection $mustQueries;
    protected Collection $mustNotQueries;
    protected Collection $shouldQueries;
    protected Collection $filterQueries;

    /**
     * @param ElasticQueryWizard $wizard
     * @param Model|string $subject
     * @throws \Throwable
     */
    public function __construct(ElasticQueryWizard $wizard, $subject)
    {
        if (! (is_subclass_of($subject, Model::class) && method_exists($subject, 'searchQuery'))) {
            throw new InvalidSubject('$subject must be a model that uses `ElasticScoutDriverPlus\Searchable` trait');
        }

        $subject = $subject::searchQuery([]);

        $this->mainBoolQuery = Query::bool();
        $this->mustQueries = collect([]);
        $this->mustNotQueries = collect([]);
        $this->shouldQueries = collect([]);
        $this->filterQueries = collect([]);

        parent::__construct($wizard, $subject);
    }

    /**
     * @param Closure|QueryBuilderInterface|array $query
     * @param string|int|null $key
     *
     * @return Closure|QueryBuilderInterface|array|null
     */
    public function must($query = null, $key = null)
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
     * @return Closure|QueryBuilderInterface|array|null
     */
    public function mustNot($query = null, $key = null)
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
     * @return Closure|QueryBuilderInterface|array|null
     */
    public function should($query = null, $key = null)
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
     * @return Closure|QueryBuilderInterface|array|null
     */
    public function filter($query = null, $key = null)
    {
        if ($key === null || ! $this->filterQueries->has($key)) {
            $this->filterQueries->put($key, value($query));
        }

        return $this->filterQueries->get($key);
    }

    /**
     * @return $this
     */
    public function withTrashed(): self
    {
        $this->mainBoolQuery->withTrashed();
        return $this;
    }

    /**
     * @return $this
     */
    public function onlyTrashed(): self
    {
        $this->mainBoolQuery->onlyTrashed();
        return $this;
    }

    protected function buildMainBoolQuery(): array
    {
        $parameters = [
            'must' => $this->mustQueries,
            'mustNot' => $this->mustNotQueries,
            'should' => $this->shouldQueries,
            'filter' => $this->filterQueries
        ];

        foreach($parameters as $key => $queries) {
            if (empty($queries)) {
                continue;
            }

            foreach($queries as $query) {
                $this->mainBoolQuery->{$key}($query);
            }
        }

        if (! $this->mainBoolQuery->hasParameter('must')) {
            $this->mainBoolQuery->must(Query::matchAll());
        }

        return $this->mainBoolQuery->buildQuery();
    }

    /**
     * @return $this
     */
    public function addEloquentQueryCallback(callable $callback): self
    {
        $this->eloquentQueryCallbacks[] = $callback;
        return $this;
    }

    /**
     * @return $this
     */
    public function addModelQueryCallback(callable $callback): self
    {
        $this->modelQueryCallbacks[] = $callback;
        return $this;
    }

    public function handle(): ElasticQueryHandler
    {
        return $this->handleAppends()
            ->handleFields()
            ->handleIncludes()
            ->handleFilters()
            ->handleSorts()
            ->handleSubject();
    }

    protected function handleSubject(): self
    {
        $this->getSubject()
            ->setQuery($this->buildMainBoolQuery())
            ->setQueryCallback(function() {
                $args = func_get_args();
                foreach($this->eloquentQueryCallbacks as $callback) {
                    call_user_func_array($callback, $args);
                }
            })
            ->setModelCallback(function() {
                $args = func_get_args();
                foreach($this->modelQueryCallbacks as $callback) {
                    call_user_func_array($callback, $args);
                }
            });

        return $this;
    }

    protected function handleFields(): self
    {
        $requestedFields = $this->wizard->getFields();
        $defaultFieldsKey = $this->wizard->getDefaultFieldsKey();
        $modelFields = $requestedFields->get($defaultFieldsKey);

        if (!empty($modelFields)) {
            $modelFields = $this->wizard->prependFieldsWithKey($modelFields);
            $this->addEloquentQueryCallback(function(EloquentBuilder $eloquentBuilder) use ($modelFields) {
                return $eloquentBuilder->select($modelFields);
            });
        }

        return $this;
    }

    protected function handleIncludes(): self
    {
        $requestedIncludes = $this->wizard->getIncludes();
        $handlers = $this->wizard->getAllowedIncludes();

        $this->addEloquentQueryCallback(function(EloquentBuilder $eloquentBuilder, SearchResponse $response) use ($requestedIncludes, $handlers) {
            $requestedIncludes->each(function($include) use (&$eloquentBuilder, &$response, $handlers) {
                /** @var AbstractElasticInclude $handler */
                $handler = $handlers->get($include);
                if ($handler) {
                    $handler->setSearchResponse($response);
                    $handler->handle($this, $eloquentBuilder);
                }
            });
        });

        return $this;
    }

    protected function handleFilters(): self
    {
        $requestedFilters = $this->wizard->getFilters();
        $handlers = $this->wizard->getAllowedFilters();

        $requestedFilters->each(function($value, $name) use ($handlers) {
            /** @var AbstractElasticFilter|AbstractEloquentFilter $handler */
            $handler = $handlers->get($name);
            if ($handler instanceof AbstractElasticFilter) {
                $handler->handle($this, $this->subject, $value);
            } elseif ($handler instanceof AbstractEloquentFilter) {
                $this->addEloquentQueryCallback(function(EloquentBuilder $eloquentBuilder) use ($handler, $value) {
                    $handler->handle($this, $eloquentBuilder, $value);
                });
            }
        });

        return $this;
    }

    protected function handleSorts(): self
    {
        $requestedSorts = $this->wizard->getSorts();
        $handlers = $this->wizard->getAllowedSorts();

        $requestedSorts->each(function(Sort $sort) use ($handlers) {
            /** @var AbstractElasticSort $handler */
            $handler = $handlers->get($sort->getField());
            if ($handler) {
                $handler->handle($this, $this->subject, $sort->getDirection());
            }
        });

        return $this;
    }

    protected function handleAppends(): self
    {
        $requestedAppends = $this->wizard->getAppends()->toArray();

        if (!empty($requestedAppends)) {
            $this->addModelQueryCallback(function(Model $model) use ($requestedAppends) {
                return $model->append($requestedAppends);
            });
        }

        return $this;
    }
}
