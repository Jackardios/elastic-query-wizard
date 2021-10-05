<?php

namespace Jackardios\ElasticQueryWizard\Handlers;

use ElasticScoutDriverPlus\Builders\BoolQueryBuilder;
use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use ElasticScoutDriverPlus\Exceptions\QueryBuilderException;
use ElasticScoutDriverPlus\Support\Query;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Jackardios\QueryWizard\Abstracts\Handlers\AbstractQueryHandler;
use Jackardios\QueryWizard\Exceptions\InvalidSubject;
use Jackardios\QueryWizard\Handlers\Eloquent\Filters\AbstractEloquentFilter;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\AbstractEloquentInclude;
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
    protected static array $baseIncludeHandlerClasses = [AbstractEloquentInclude::class];
    protected static array $baseSortHandlerClasses = [AbstractElasticSort::class];

    /** @var callable[] */
    protected array $eloquentQueryCallbacks = [];

    /** @var callable[] */
    protected array $modelQueryCallbacks = [];

    protected BoolQueryBuilder $mainBoolQuery;
    protected BoolQueryBuilder $filtersBoolQuery;

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
        $this->filtersBoolQuery = Query::bool();

        parent::__construct($wizard, $subject);
    }

    public function getMainBoolQuery(): BoolQueryBuilder
    {
        return $this->mainBoolQuery;
    }

    public function getFiltersBoolQuery(): BoolQueryBuilder
    {
        return $this->filtersBoolQuery;
    }

    protected function buildMainBoolQuery(): array
    {
        try {
            $buildMainQuery = $this->mainBoolQuery->buildQuery();
        } catch (QueryBuilderException $e) {
            $buildMainQuery = $this->mainBoolQuery->must(Query::matchAll())->buildQuery();
        }

        try {
            $builtFiltersQuery = $this->filtersBoolQuery->buildQuery();
            $buildMainQuery['bool']['filter'][] = $builtFiltersQuery;
        } catch (QueryBuilderException $e) {}

        return $buildMainQuery;
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

        $this->addEloquentQueryCallback(function(EloquentBuilder $eloquentBuilder) use ($requestedIncludes, $handlers) {
            $requestedIncludes->each(function($include) use (&$eloquentBuilder, $handlers) {
                $handler = $handlers->get($include);
                if ($handler) {
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
