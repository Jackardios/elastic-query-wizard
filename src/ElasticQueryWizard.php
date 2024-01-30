<?php

namespace Jackardios\ElasticQueryWizard;

use Elastic\Adapter\Search\SearchResult;
use Elastic\ScoutDriverPlus\Builders\SearchParametersBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
 * @mixin SearchParametersBuilder
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

    /** @var SearchParametersBuilder */
    protected $subject;

    /** @var array<int, callable(Builder, SearchResult): void> $eloquentQueryCallbacks */
    protected array $eloquentQueryCallbacks = [];

    /** @var array<int, callable(Collection): Collection> $eloquentCollectionCallbacks */
    protected array $eloquentCollectionCallbacks = [];

    protected ElasticRootBoolQuery $rootBoolQuery;

    protected string $modelTable;

    public function __construct(Model|string $subject, ?QueryParametersManager $parametersManager = null)
    {
        if (! (is_subclass_of($subject, Model::class) && method_exists($subject, 'searchQuery'))) {
            throw new InvalidSubject('$subject must be a model that uses `Elastic\ScoutDriverPlus\Searchable` trait');
        }

        $this->modelTable = $subject instanceof Model ? $subject->getTable() : (new $subject)->getTable();
        $subject = $subject::searchQuery([]);

        $this->rootBoolQuery = new ElasticRootBoolQuery();

        parent::__construct($subject, $parametersManager);
    }

    protected function defaultFieldsKey(): string
    {
        return $this->modelTable;
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

    public function getSubject(): SearchParametersBuilder
    {
        return $this->subject;
    }

    public function getRootBoolQuery(): ElasticRootBoolQuery
    {
        return $this->rootBoolQuery;
    }

    /**
     * Set the callback that should have an opportunity to modify the database query.
     * This method overrides the Scout Query Builder method
     *
     * @param  callable(Builder, SearchResult): void  $callback
     * @return $this
     */
    public function addEloquentQueryCallback(callable $callback): self
    {
        $this->eloquentQueryCallbacks[] = $callback;
        return $this;
    }

    /**
     * Set the callback that should have an opportunity to modify the database query.
     * This method overrides the Scout Query Builder method
     *
     * @param  callable(Collection): Collection  $callback
     * @return $this
     */
    public function addEloquentCollectionCallback(callable $callback): self
    {
        $this->eloquentCollectionCallbacks[] = $callback;
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
            ->query($this->getRootBoolQuery()->buildQuery())
            ->setEloquentQueryCallback(function() {
                $args = func_get_args();
                foreach($this->eloquentQueryCallbacks as $callback) {
                    call_user_func_array($callback, $args);
                }
            })
            ->setEloquentCollectionCallback(function(Collection $collection) {
                foreach($this->eloquentCollectionCallbacks as $callback) {
                    $collection = call_user_func($callback, $collection);
                }

                return $collection;
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

        $this->addEloquentQueryCallback(function(EloquentBuilder $eloquentBuilder, SearchResult $searchResult) use ($requestedIncludes, $handlers) {
            $requestedIncludes->each(function($include) use (&$eloquentBuilder, &$searchResult, $handlers) {
                /** @var ElasticInclude $handler */
                $handler = $handlers->get($include);

                $handler?->setSearchResult($searchResult)->handle($this, $eloquentBuilder);
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
            $handler?->handle($this, $this->subject, $sort->getDirection());
        });

        return $this;
    }

    protected function handleAppends(): self
    {
        $requestedAppends = $this->getAppends()->toArray();

        if (!empty($requestedAppends)) {
            $this->addEloquentCollectionCallback(function(Collection $collection) use ($requestedAppends) {
                return $collection->append($requestedAppends);
            });
        }

        return $this;
    }
}
