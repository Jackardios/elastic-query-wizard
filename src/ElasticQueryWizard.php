<?php

namespace Jackardios\ElasticQueryWizard;

use ElasticAdapter\Search\SearchResponse;
use ElasticScoutDriverPlus\Builders\BoolQueryBuilder;
use ElasticScoutDriverPlus\Builders\QueryBuilderInterface;
use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use ElasticScoutDriverPlus\Support\Query;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Includes\CountInclude;
use Jackardios\ElasticQueryWizard\Includes\RelationshipInclude;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesFilters;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Concerns\HandlesSorts;
use Jackardios\QueryWizard\Eloquent\EloquentFilter;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Values\Sort;

/**
 * @mixin SearchRequestBuilder
 * @method static static for(Model|string $subject, QueryParametersManager|null $parametersManager = null)
 */
class ElasticQueryWizard extends AbstractQueryWizard
{
    use HandlesAppends;
    use HandlesFields;
    use HandlesFilters;
    use HandlesIncludes;
    use HandlesSorts;

    protected array $baseFilterHandlerClasses = [EloquentFilter::class, ElasticFilter::class];
    protected array $baseIncludeHandlerClasses = [ElasticInclude::class];
    protected array $baseSortHandlerClasses = [ElasticSort::class];

    /** @var SearchRequestBuilder */
    protected $subject;

    /** @var callable[] */
    protected array $eloquentQueryCallbacks = [];

    /** @var callable[] */
    protected array $modelQueryCallbacks = [];

    protected BoolQueryBuilder $mainBoolQuery;
    protected Collection $mustQueries;
    protected Collection $mustNotQueries;
    protected Collection $shouldQueries;
    protected Collection $filterQueries;

    public function __construct(Model|string $subject, ?QueryParametersManager $parametersManager = null)
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

        parent::__construct($subject, $parametersManager);
    }

    protected function defaultFieldsKey(): string
    {
        return $this->subject->getModel()->getTable();
    }

    /**
     * Set the callback that should have an opportunity to modify the database query.
     * This method overrides the Scout Query Builder method
     *
     * @param  callable  $callback
     * @return $this
     */
    public function query(callable $callback): self
    {
        $this->addEloquentQueryCallback($callback);

        return $this;
    }

    public function makeDefaultFilterHandler(string $filterName): TermFilter
    {
        return new TermFilter($filterName);
    }

    /**
     * @param string $includeName
     * @return RelationshipInclude|CountInclude
     */
    public function makeDefaultIncludeHandler(string $includeName): ElasticInclude
    {
        $countSuffix = config('query-wizard.count_suffix');
        if (Str::endsWith($includeName, $countSuffix)) {
            $relation = Str::before($includeName, $countSuffix);
            return new CountInclude($relation, $includeName);
        }
        return new RelationshipInclude($includeName);
    }

    public function makeDefaultSortHandler(string $sortName): FieldSort
    {
        return new FieldSort($sortName);
    }

    public function getSubject(): SearchRequestBuilder
    {
        return $this->subject;
    }

    /**
     * @param Closure|QueryBuilderInterface|array $query
     * @param string|int|null $key
     *
     * @return QueryBuilderInterface|array|null
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
     * @return QueryBuilderInterface|array|null
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
     * @return QueryBuilderInterface|array|null
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
     * @return QueryBuilderInterface|array|null
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

    public function build(): static
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
        $this->subject
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
        $requestedFields = $this->getFields();
        $defaultFieldsKey = $this->getDefaultFieldsKey();
        $modelFields = $requestedFields->get($defaultFieldsKey);

        if (!empty($modelFields)) {
            $modelFields = $this->prependFieldsWithKey($modelFields);
            $this->addEloquentQueryCallback(function(EloquentBuilder $eloquentBuilder) use ($modelFields) {
                return $eloquentBuilder->select($modelFields);
            });
        }

        return $this;
    }

    protected function handleIncludes(): self
    {
        $requestedIncludes = $this->getIncludes();
        $handlers = $this->getAllowedIncludes();

        $this->addEloquentQueryCallback(function(EloquentBuilder $eloquentBuilder, SearchResponse $response) use ($requestedIncludes, $handlers) {
            $requestedIncludes->each(function($include) use (&$eloquentBuilder, &$response, $handlers) {
                /** @var ElasticInclude $handler */
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
        $requestedFilters = $this->getFilters();
        $handlers = $this->getAllowedFilters();

        $requestedFilters->each(function($value, $name) use ($handlers) {
            /** @var ElasticFilter|EloquentFilter $handler */
            $handler = $handlers->get($name);
            if ($handler instanceof ElasticFilter) {
                $handler->handle($this, $this->subject, $value);
            } elseif ($handler instanceof EloquentFilter) {
                $this->addEloquentQueryCallback(function(EloquentBuilder $eloquentBuilder) use ($handler, $value) {
                    $handler->handle($this, $eloquentBuilder, $value);
                });
            }
        });

        return $this;
    }

    protected function handleSorts(): self
    {
        $requestedSorts = $this->getSorts();
        $handlers = $this->getAllowedSorts();

        $requestedSorts->each(function(Sort $sort) use ($handlers) {
            /** @var ElasticSort $handler */
            $handler = $handlers->get($sort->getField());
            if ($handler) {
                $handler->handle($this, $this->subject, $sort->getDirection());
            }
        });

        return $this;
    }

    protected function handleAppends(): self
    {
        $requestedAppends = $this->getAppends()->toArray();

        if (!empty($requestedAppends)) {
            $this->addModelQueryCallback(function(Model $model) use ($requestedAppends) {
                return $model->append($requestedAppends);
            });
        }

        return $this;
    }
}
