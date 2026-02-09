<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\ElasticQueryWizard\Filters\AbstractElasticFilter;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Includes\AbstractElasticInclude;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Search\SearchResult;
use Jackardios\QueryWizard\BaseQueryWizard;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * @mixin SearchBuilder
 */
class ElasticQueryWizard extends BaseQueryWizard
{
    /** @var SearchBuilder */
    protected mixed $subject;

    /** @var array<int, callable(Builder, SearchResult): void> */
    protected array $eloquentQueryCallbacks = [];

    /** @var array<int, callable(Collection): Collection> */
    protected array $eloquentCollectionCallbacks = [];

    protected string $modelClass;

    private bool $proxyModified = false;

    public function __construct(
        Model|string $subject,
        ?QueryParametersManager $parameters = null,
        ?QueryWizardConfig $config = null,
        ?ResourceSchemaInterface $schema = null,
    ) {
        if (! (is_subclass_of($subject, Model::class) && method_exists($subject, 'searchQuery'))) {
            throw new \InvalidArgumentException('$subject must be a model that uses `Jackardios\EsScoutDriver\Searchable` trait');
        }

        $this->modelClass = is_string($subject) ? $subject : $subject::class;
        $this->subject = $this->modelClass::searchQuery();
        $this->originalSubject = clone $this->subject;
        $this->parameters = $parameters ?? app(QueryParametersManager::class);
        $this->config = $config ?? app(QueryWizardConfig::class);
        $this->schema = $schema;
    }

    public static function for(Model|string $subject, ?QueryParametersManager $parameters = null): static
    {
        return new static($subject, $parameters);
    }

    public static function forSchema(string|ResourceSchemaInterface $schema): static
    {
        $schema = is_string($schema) ? app($schema) : $schema;

        /** @var class-string<Model> $modelClass */
        $modelClass = $schema->model();

        return new static(
            $modelClass,
            app(QueryParametersManager::class),
            app(QueryWizardConfig::class),
            $schema
        );
    }

    public function getSubject(): SearchBuilder
    {
        return $this->subject;
    }

    public function boolQuery(): BoolQuery
    {
        return $this->subject->boolQuery();
    }

    /**
     * @param callable(Builder, SearchResult): void $callback
     */
    public function addEloquentQueryCallback(callable $callback): static
    {
        $this->eloquentQueryCallbacks[] = $callback;

        return $this;
    }

    /**
     * @param callable(Collection): Collection $callback
     */
    public function addEloquentCollectionCallback(callable $callback): static
    {
        $this->eloquentCollectionCallbacks[] = $callback;

        return $this;
    }

    // === Abstract implementations ===

    protected function normalizeStringToFilter(string $name): FilterInterface
    {
        return TermFilter::make($name);
    }

    protected function normalizeStringToSort(string $name): SortInterface
    {
        $property = ltrim($name, '-');

        return FieldSort::make($property);
    }

    protected function normalizeStringToInclude(string $name): IncludeInterface
    {
        return RelationshipInclude::fromString($name, $this->config->getCountSuffix());
    }

    protected function applyFields(array $fields): void
    {
        /** @var Model $model */
        $model = new $this->modelClass;
        $keyName = $model->getKeyName();
        $scoutKeyName = $model->getScoutKeyName();

        $requiredFields = array_unique(array_filter([$keyName, $scoutKeyName]));
        $fields = array_values(array_unique(array_merge($requiredFields, $fields)));

        $this->addEloquentQueryCallback(function (Builder $eloquentBuilder) use ($fields) {
            $eloquentBuilder->select($fields);
        });
    }

    public function getResourceKey(): string
    {
        if ($this->schema !== null) {
            return $this->schema->type();
        }

        return Str::camel(class_basename($this->modelClass));
    }

    // === Filter / Include hooks ===

    protected function applyFilter(FilterInterface $filter, mixed $preparedValue): void
    {
        if ($filter instanceof AbstractElasticFilter) {
            $filter->handle($this->subject, $preparedValue);
        } else {
            $this->subject = $filter->apply($this->subject, $preparedValue);
        }
    }

    /**
     * @param  array<int, string>  $validRequestedIncludes
     * @param  array<string, IncludeInterface>  $includesIndex
     */
    protected function applyValidatedIncludes(array $validRequestedIncludes, array $includesIndex): void
    {
        $elasticIncludes = [];
        $baseIncludes = [];

        foreach ($validRequestedIncludes as $includeName) {
            $include = $includesIndex[$includeName];
            if ($include instanceof AbstractElasticInclude) {
                $elasticIncludes[] = $include;
            } else {
                $baseIncludes[] = $include;
            }
        }

        if (! empty($baseIncludes)) {
            $this->addEloquentQueryCallback(
                function (Builder $builder, SearchResult $searchResult) use ($baseIncludes) {
                    foreach ($baseIncludes as $include) {
                        $include->apply($builder);
                    }
                }
            );
        }

        if (! empty($elasticIncludes)) {
            $this->addEloquentQueryCallback(
                function (Builder $builder, SearchResult $searchResult) use ($elasticIncludes) {
                    foreach ($elasticIncludes as $include) {
                        $include->setSearchResult($searchResult)->handleEloquent($builder);
                    }
                }
            );
        }
    }

    // === Build ===

    public function build(): mixed
    {
        if ($this->built) {
            return $this->subject;
        }

        $this->applyTapCallbacks();
        $this->applyFiltersToSubject();
        $this->applySortsToSubject();
        $this->applyIncludesToSubject();
        $this->applyFieldsToSubject();
        $this->finalizeSubject();

        $this->built = true;

        return $this->subject;
    }

    protected function invalidateBuild(): void
    {
        if ($this->proxyModified) {
            throw new \LogicException(
                'Cannot modify query wizard configuration after calling query builder methods. '
                .'Call all configuration methods (allowedFilters, allowedSorts, etc.) before query builder methods.'
            );
        }

        parent::invalidateBuild();
        $this->eloquentQueryCallbacks = [];
        $this->eloquentCollectionCallbacks = [];
    }

    protected function finalizeSubject(): void
    {
        $appends = $this->getValidRequestedAppends();
        $requestedFields = $this->parameters->getFields();
        $resourceKey = $this->getResourceKey();
        $rootFields = $requestedFields->get($resourceKey, []);

        $eloquentQueryCallbacks = $this->eloquentQueryCallbacks;
        $eloquentCollectionCallbacks = $this->eloquentCollectionCallbacks;

        $this->subject
            ->modifyQuery(function (Builder $builder, array $rawResult) use ($eloquentQueryCallbacks) {
                $searchResult = new SearchResult($rawResult, fn () => collect());
                foreach ($eloquentQueryCallbacks as $callback) {
                    $callback($builder, $searchResult);
                }
            })
            ->modifyModels(function (Collection $collection) use ($appends, $rootFields, $eloquentCollectionCallbacks) {
                foreach ($eloquentCollectionCallbacks as $callback) {
                    $collection = call_user_func($callback, $collection);
                }

                if (! empty($appends)) {
                    $this->applyAppendsTo($collection);
                }

                if (! empty($rootFields) && ! in_array('*', $rootFields)) {
                    /** @var Model|null $firstModel */
                    $firstModel = $collection->first();
                    if ($firstModel) {
                        $newHidden = array_values(array_unique([
                            ...$firstModel->getHidden(),
                            ...array_diff(array_keys($firstModel->getAttributes()), $rootFields),
                        ]));
                        $collection = $collection->setHidden($newHidden);
                    }
                }

                return $collection;
            });
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->build();

        $result = $this->subject->$name(...$arguments);

        if ($result === $this->subject) {
            $this->proxyModified = true;

            return $this;
        }

        return $result;
    }

    public function __clone(): void
    {
        parent::__clone();
        $this->proxyModified = false;
    }
}
