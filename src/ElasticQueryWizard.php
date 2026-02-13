<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Groups\GroupInterface;
use Jackardios\ElasticQueryWizard\Includes\AbstractElasticInclude;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Search\SearchResult;
use Jackardios\QueryWizard\BaseQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesRelationPostProcessing;
use Jackardios\QueryWizard\Concerns\HandlesSafeRelationSelect;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Exceptions\InvalidFilterQuery;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\Includes\RelationshipInclude;
use Jackardios\QueryWizard\QueryParametersManager;
use Jackardios\QueryWizard\Schema\ResourceSchemaInterface;

/**
 * Query wizard for Elasticsearch queries via Scout.
 *
 * Handles list queries with filters, sorts, includes, fields, and appends.
 * Supports Elasticsearch 8.x and 9.x.
 *
 * ES 9.x compatibility notes:
 * - Don't use `force_source` highlighting parameter (removed in ES 9.x)
 * - For `random_score`, specify `field` explicitly (default changed from `_id` to `_seq_no`)
 * - Don't use histogram aggregation on boolean fields (use terms aggregation instead)
 *
 * Query execution methods (delegated to SearchBuilder):
 * @method SearchResult execute() Execute query and get full SearchResult (hits, models, documents, aggregations, suggestions)
 * @method \Jackardios\EsScoutDriver\Search\Paginator paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null) Paginate results (call ->withModels() or ->withDocuments() on result)
 * @method \Jackardios\EsScoutDriver\Search\Hit|null first() Get first hit (use ->model() to get Model)
 * @method \Jackardios\EsScoutDriver\Search\Hit firstOrFail() Get first hit or throw ModelNotFoundException
 * @method int count() Get total count without loading models
 * @method array raw() Get raw Elasticsearch response array
 *
 * @phpstan-consistent-constructor
 *
 * @mixin SearchBuilder
 */
class ElasticQueryWizard extends BaseQueryWizard
{
    use HandlesSafeRelationSelect;
    use HandlesRelationPostProcessing;

    /** @var SearchBuilder */
    protected mixed $subject;

    /** @var array<int, Closure(Builder, array): mixed> */
    protected array $queryModifiers = [];

    /** @var array<int, Closure(Collection): Collection> */
    protected array $modelModifiers = [];

    /** @var array<int, Closure(Builder, SearchResult): mixed> */
    protected array $buildQueryModifiers = [];

    /** @var array<int, Closure(SearchBuilder): mixed> */
    protected array $searchBuilderModifiers = [];

    protected string $modelClass;

    /** @var array<int, string> */
    protected array $validatedRequestedRootFields = [];

    /** @var array<string> */
    protected array $safeRootHiddenFields = [];

    /** @var array{fields: array<string>, relations: array<string, mixed>} */
    protected array $relationFieldTree;

    /** @var array{appends: array<string>, relations: array<string, mixed>} */
    protected array $appendTree;

    protected bool $relationFieldTreePrepared = false;

    protected bool $appendTreePrepared = false;

    /** @var array<string, bool> */
    private static array $searchBuilderFluentMethods = [];

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
        $this->resolveParametersFromContainer = $parameters === null;
        $this->parameters = $parameters ?? app(QueryParametersManager::class);
        $this->config = $config ?? app(QueryWizardConfig::class);
        $this->schema = $schema;
        $this->relationFieldTree = $this->emptyRelationFieldTree();
        $this->appendTree = $this->emptyAppendTree();
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
            null,
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
        if ($this->built) {
            $this->proxyModified = true;
        }

        return $this->subject->boolQuery();
    }

    /**
     * Apply custom SearchBuilder mutations declaratively (before/after build).
     *
     * @param Closure(SearchBuilder): mixed $callback Return value is ignored.
     */
    public function tapSearchBuilder(Closure $callback): static
    {
        return $this->queueSearchBuilderMutation($callback);
    }

    /**
     * Add a callback to modify the Eloquent query before loading models.
     *
     * @param Closure(Builder, array): mixed $callback Return value is ignored.
     */
    public function modifyQuery(Closure $callback): static
    {
        $this->assertBuildCallbackCanBeAdded('modifyQuery');
        $this->queryModifiers[] = $callback;

        return $this;
    }

    /**
     * Add a callback to modify the loaded Eloquent collection.
     *
     * @param Closure(Collection): Collection $callback
     */
    public function modifyModels(Closure $callback): static
    {
        $this->assertBuildCallbackCanBeAdded('modifyModels');
        $this->modelModifiers[] = $callback;

        return $this;
    }

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
        return RelationshipInclude::fromString($name, $this->config->getCountSuffix(), $this->config->getExistsSuffix());
    }

    protected function applyFields(array $fields): void
    {
        $requestedFields = $fields;
        $fields = $this->applySafeRootFieldRequirements($fields);
        $this->safeRootHiddenFields = array_values(array_diff($fields, $requestedFields));
        $this->validatedRequestedRootFields = array_values(array_unique($requestedFields));

        /** @var Model $model */
        $model = new $this->modelClass();
        $keyName = $model->getKeyName();
        $scoutKeyName = $model->getScoutKeyName();

        $requiredFields = array_unique(array_filter([$keyName, $scoutKeyName]));
        $fields = array_values(array_unique(array_merge($requiredFields, $fields)));

        $this->addBuildQueryModifier(function (Builder $eloquentBuilder) use ($fields) {
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

    /**
     * Apply post-processing to externally-loaded results.
     *
     * Use this method when executing queries outside the wizard (e.g., via getSubject())
     * to apply sparse fieldsets and appends to loaded models.
     *
     * @template T of Model|\Traversable<mixed>|array<mixed>
     *
     * @param  T  $results  Single model, collection, or iterable of models
     * @return T The same results with post-processing applied
     */
    public function applyPostProcessingTo(mixed $results): mixed
    {
        $this->build();
        $this->applyPostProcessingToResults($results);

        return $results;
    }

    /**
     * Apply post-processing to results (appends + relation field hiding).
     *
     * @param  Model|\Traversable<mixed>|array<mixed>  $results
     */
    protected function applyPostProcessingToResults(mixed $results): void
    {
        if (! empty($this->safeRootHiddenFields)) {
            if ($results instanceof Model) {
                $results->makeHidden($this->safeRootHiddenFields);
            } else {
                foreach ($results as $item) {
                    if ($item instanceof Model) {
                        $item->makeHidden($this->safeRootHiddenFields);
                    }
                }
            }
        }

        $this->applyRelationPostProcessingToResults($results, $this->appendTree, $this->relationFieldTree);

        $rootFields = $this->validatedRequestedRootFields;
        if (! empty($rootFields) && ! in_array('*', $rootFields)) {
            if ($results instanceof Model) {
                $this->hideModelAttributesExcept($results, $rootFields);
            } elseif ($results instanceof Collection) {
                /** @var Model|null $firstModel */
                $firstModel = $results->first();
                if ($firstModel) {
                    $newHidden = array_values(array_unique([
                        ...$firstModel->getHidden(),
                        ...array_diff(array_keys($firstModel->getAttributes()), $rootFields),
                    ]));
                    $results->each(fn(Model $model) => $model->setHidden($newHidden));
                }
            }
        }
    }

    protected function applyFilter(FilterInterface $filter, mixed $preparedValue): void
    {
        $this->subject = $filter->apply($this->subject, $preparedValue);
    }

    /**
     * Override to handle filter groups.
     *
     * Groups contain child filters and need special handling:
     * - Child filter names are registered as allowed filters
     * - Child filter values are collected and passed to the group as an array
     */
    protected function applyFiltersToSubject(): void
    {
        $filters = $this->getEffectiveFilters();
        $requestedFilterNames = $this->extractRequestedFilterNames();

        $this->validateFiltersLimit(count($requestedFilterNames));

        // Build allowed filter names including child filter names from groups
        // Group names are excluded - they are not valid filter keys in URL
        $allowedFilterNames = [];
        $groupChildNames = [];

        foreach ($filters as $filter) {
            if ($filter instanceof GroupInterface) {
                $groupChildNames = array_merge($groupChildNames, $filter->getChildFilterNames());
            } else {
                $allowedFilterNames[] = $filter->getName();
            }
        }

        // Deduplicate: a filter name may appear both at root level and inside a group
        $expandedAllowedNames = array_values(array_unique(
            array_merge($allowedFilterNames, $groupChildNames)
        ));
        $allowedFilterNamesIndex = array_flip($expandedAllowedNames);
        $prefixIndex = $this->buildPrefixIndex($expandedAllowedNames);

        // Validate requested filter names
        foreach ($requestedFilterNames as $filterName) {
            if (! $this->isValidFilterName($filterName, $allowedFilterNamesIndex, $prefixIndex)) {
                if (! $this->config->isInvalidFilterQueryExceptionDisabled()) {
                    throw InvalidFilterQuery::filtersNotAllowed(
                        collect([$filterName]),
                        collect($expandedAllowedNames)
                    );
                }
            }
        }

        // Collect all child names from groups upfront to skip duplicates at root level
        $allGroupChildNames = array_flip($groupChildNames);

        // Apply filters
        foreach ($filters as $filter) {
            if ($filter instanceof GroupInterface) {
                // Collect child filter values for this group
                $childValues = $this->collectGroupChildValuesForGroup($filter);

                if (empty($childValues)) {
                    continue;
                }

                // Apply the group with collected child values
                $this->applyFilter($filter, $childValues);
            } else {
                // Skip if this filter name is handled by a group
                if (isset($allGroupChildNames[$filter->getName()])) {
                    continue;
                }

                $value = $this->resolveFilterValue($filter);

                if ($value === null) {
                    continue;
                }

                $preparedValue = $filter->prepareValue($value);

                if ($preparedValue === null) {
                    continue;
                }

                $this->applyFilter($filter, $preparedValue);
            }
        }
    }

    /**
     * Collect filter values for all children of a group.
     *
     * @param GroupInterface $group The group to collect values for
     * @return array<string, mixed> Map of child filter names to their prepared values
     */
    protected function collectGroupChildValuesForGroup(GroupInterface $group): array
    {
        $childValues = [];

        foreach ($group->getChildren() as $child) {
            $childName = $child->getName();

            if ($child instanceof GroupInterface) {
                // Recursively collect values for nested groups
                $nestedValues = $this->collectGroupChildValuesForGroup($child);
                if (! empty($nestedValues)) {
                    $childValues[$childName] = $nestedValues;
                    // Also merge nested child values for the group to use
                    $childValues = array_merge($childValues, $nestedValues);
                }
            } else {
                $value = $this->resolveFilterValue($child);

                if ($value === null) {
                    continue;
                }

                $preparedValue = $child->prepareValue($value);

                if ($preparedValue === null) {
                    continue;
                }

                $childValues[$childName] = $preparedValue;
            }
        }

        return $childValues;
    }

    /**
     * @param  array<int, string>  $validRequestedIncludes
     * @param  array<string, IncludeInterface>  $includesIndex
     */
    protected function applyValidatedIncludes(array $validRequestedIncludes, array $includesIndex): void
    {
        $elasticIncludes = [];
        $relationshipIncludes = [];
        $otherIncludes = [];
        $relationshipPaths = [];

        foreach ($validRequestedIncludes as $includeName) {
            $include = $includesIndex[$includeName];
            if ($include instanceof AbstractElasticInclude) {
                $elasticIncludes[] = $include;
            } elseif ($include->getType() === 'relationship') {
                $relationshipIncludes[] = $include;
                $relationshipPaths[] = $include->getRelation();
            } else {
                $otherIncludes[] = $include;
            }
        }

        /** @var Model $model */
        $model = new $this->modelClass();
        $this->prepareSafeRelationSelectPlan($model, $relationshipPaths);

        if (! empty($otherIncludes)) {
            $this->addBuildQueryModifier(
                function (Builder $builder, SearchResult $searchResult) use ($otherIncludes) {
                    foreach ($otherIncludes as $include) {
                        $include->apply($builder);
                    }
                }
            );
        }

        if (! empty($relationshipIncludes)) {
            $safeRelationSelectColumnsByPath = $this->safeRelationSelectColumnsByPath;

            $this->addBuildQueryModifier(
                function (Builder $builder, SearchResult $searchResult) use ($relationshipIncludes, $safeRelationSelectColumnsByPath) {
                    foreach ($relationshipIncludes as $include) {
                        $relationPath = $include->getRelation();
                        $columns = $safeRelationSelectColumnsByPath[$relationPath] ?? null;

                        if ($columns === null) {
                            $include->apply($builder);
                            continue;
                        }

                        $builder->with([
                            $relationPath => static function ($query) use ($columns): void {
                                $query->select($columns);
                            },
                        ]);
                    }
                }
            );
        }

        if (! empty($elasticIncludes)) {
            $this->addBuildQueryModifier(
                function (Builder $builder, SearchResult $searchResult) use ($elasticIncludes) {
                    foreach ($elasticIncludes as $include) {
                        $include->setSearchResult($searchResult)->apply($builder);
                    }
                }
            );
        }
    }

    public function build(): mixed
    {
        $currentScopeSignature = $this->resolveBuildScopeSignature();

        if ($this->built) {
            if ($this->builtScopeSignature === $currentScopeSignature) {
                return $this->subject;
            }
            $this->invalidateBuild();
        }

        $this->applyTapCallbacks();
        $this->applySearchBuilderModifiers();
        $this->applyFiltersToSubject();
        $this->applySortsToSubject();
        $this->applyIncludesToSubject();
        $this->applyFieldsToSubject();
        $this->finalizeSubject();

        $this->built = true;
        $this->builtScopeSignature = $currentScopeSignature;

        return $this->subject;
    }

    protected function invalidateBuild(): void
    {
        if ($this->proxyModified) {
            throw new \LogicException(
                'Cannot modify query wizard configuration after calling query builder methods. '
                . 'Call all configuration methods (allowedFilters, allowedSorts, etc.) before query builder methods.'
            );
        }

        $this->resetSafeRelationSelectState();
        $this->relationFieldTree = $this->emptyRelationFieldTree();
        $this->relationFieldTreePrepared = false;
        $this->appendTree = $this->emptyAppendTree();
        $this->appendTreePrepared = false;
        $this->safeRootHiddenFields = [];
        parent::invalidateBuild();
        $this->buildQueryModifiers = [];
        $this->validatedRequestedRootFields = [];
    }

    protected function finalizeSubject(): void
    {
        $this->prepareRelationFieldData();
        $this->prepareAppendTreeData();

        $queryModifiers = $this->queryModifiers;
        $buildQueryModifiers = $this->buildQueryModifiers;
        $modelModifiers = $this->modelModifiers;

        $this->subject
            ->modifyQuery(function (Builder $builder, array $rawResult) use ($queryModifiers, $buildQueryModifiers) {
                foreach ($queryModifiers as $callback) {
                    $callback($builder, $rawResult);
                }

                if ($buildQueryModifiers === []) {
                    return;
                }

                $searchResult = new SearchResult($rawResult);
                foreach ($buildQueryModifiers as $callback) {
                    $callback($builder, $searchResult);
                }
            })
            ->modifyModels(function (Collection $collection) use ($modelModifiers) {
                foreach ($modelModifiers as $callback) {
                    $collection = call_user_func($callback, $collection);
                }

                $this->applyPostProcessingToResults($collection);

                return $collection;
            });
    }

    protected function prepareRelationFieldData(): void
    {
        if ($this->relationFieldTreePrepared) {
            return;
        }

        $this->relationFieldTreePrepared = true;
        $relationFieldMap = $this->buildValidatedRelationFieldMap();
        $this->relationFieldTree = $this->buildRelationFieldTree($relationFieldMap);
    }

    protected function prepareAppendTreeData(): void
    {
        if ($this->appendTreePrepared) {
            return;
        }

        $this->appendTreePrepared = true;
        $this->appendTree = $this->getValidRequestedAppendsTree();
    }

    protected function applySearchBuilderModifiers(): void
    {
        foreach ($this->searchBuilderModifiers as $callback) {
            $callback($this->subject);
        }
    }

    /**
     * @param Closure(Builder, SearchResult): mixed $callback
     */
    protected function addBuildQueryModifier(Closure $callback): void
    {
        $this->buildQueryModifiers[] = $callback;
    }

    /**
     * @param Closure(SearchBuilder): mixed $callback
     */
    protected function queueSearchBuilderMutation(Closure $callback): static
    {
        $this->searchBuilderModifiers[] = $callback;

        if ($this->built) {
            $this->proxyModified = true;
            $callback($this->subject);
        }

        return $this;
    }

    /**
     * @param array<int, mixed> $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($this->isSearchBuilderFluentMethod($name)) {
            return $this->queueSearchBuilderMutation(
                fn(SearchBuilder $builder) => $builder->{$name}(...$arguments)
            );
        }

        if (! method_exists($this->subject, $name)) {
            throw new \BadMethodCallException(
                sprintf('Method %s::%s does not exist.', static::class, $name)
            );
        }

        $this->build();
        $result = $this->subject->$name(...$arguments);

        if ($result === $this->subject) {
            $this->proxyModified = true;

            return $this;
        }

        return $result;
    }

    private function isSearchBuilderFluentMethod(string $name): bool
    {
        if (array_key_exists($name, self::$searchBuilderFluentMethods)) {
            return self::$searchBuilderFluentMethods[$name];
        }

        if (! method_exists(SearchBuilder::class, $name)) {
            self::$searchBuilderFluentMethods[$name] = false;

            return false;
        }

        $method = new \ReflectionMethod(SearchBuilder::class, $name);
        $returnType = $method->getReturnType();

        if ($returnType === null) {
            self::$searchBuilderFluentMethods[$name] = false;

            return false;
        }

        $isFluent = false;
        if ($returnType instanceof \ReflectionNamedType) {
            $isFluent = $this->isFluentNamedReturnType($returnType);
        } elseif ($returnType instanceof \ReflectionUnionType) {
            foreach ($returnType->getTypes() as $type) {
                if ($type instanceof \ReflectionNamedType && $this->isFluentNamedReturnType($type)) {
                    $isFluent = true;

                    break;
                }
            }
        }

        self::$searchBuilderFluentMethods[$name] = $isFluent;

        return $isFluent;
    }

    private function isFluentNamedReturnType(\ReflectionNamedType $returnType): bool
    {
        $typeName = $returnType->getName();

        if ($typeName === 'self' || $typeName === 'static') {
            return true;
        }

        return is_a($typeName, SearchBuilder::class, true);
    }

    private function assertBuildCallbackCanBeAdded(string $methodName): void
    {
        if (! $this->built) {
            return;
        }

        throw new \LogicException(
            sprintf(
                'Cannot call %s() after build(). Register query/model callbacks before build().',
                $methodName
            )
        );
    }

    public function __clone(): void
    {
        parent::__clone();
        $this->proxyModified = false;
        $this->resetSafeRelationSelectState();
        $this->relationFieldTree = $this->emptyRelationFieldTree();
        $this->relationFieldTreePrepared = false;
        $this->appendTree = $this->emptyAppendTree();
        $this->appendTreePrepared = false;
        $this->safeRootHiddenFields = [];
        $this->buildQueryModifiers = [];
        $this->validatedRequestedRootFields = [];
    }
}
