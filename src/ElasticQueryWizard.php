<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
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
 *
 * @method SearchResult execute() Execute query and get full SearchResult (hits, models, documents, aggregations, suggestions)
 * @method \Jackardios\EsScoutDriver\Search\Paginator paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null) Paginate results (call ->withModels() or ->withDocuments() on result)
 * @method \Jackardios\EsScoutDriver\Search\Hit|null first() Get first hit (use ->model() to get Model)
 * @method \Jackardios\EsScoutDriver\Search\Hit firstOrFail() Get first hit or throw ModelNotFoundException
 * @method int count() Get total count without loading models
 * @method array raw() Get raw Elasticsearch response array
 *
 * @extends BaseQueryWizard<SearchBuilder>
 *
 * @phpstan-consistent-constructor
 *
 * @mixin SearchBuilder
 */
class ElasticQueryWizard extends BaseQueryWizard
{
    use HandlesRelationPostProcessing;
    use HandlesSafeRelationSelect;

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

    /** @var class-string<Model> */
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
        /** @var SearchBuilder $searchBuilder */
        $searchBuilder = $this->modelClass::searchQuery();
        $this->subject = $searchBuilder;
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
        /** @var ResourceSchemaInterface $resolvedSchema */
        $resolvedSchema = is_string($schema) ? app($schema) : $schema;

        /** @var class-string<Model> $modelClass */
        $modelClass = $resolvedSchema->model();

        return new static(
            $modelClass,
            null,
            app(QueryWizardConfig::class),
            $resolvedSchema
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
     * @param  Closure(SearchBuilder): mixed  $callback  Return value is ignored.
     */
    public function tapSearchBuilder(Closure $callback): static
    {
        return $this->queueSearchBuilderMutation($callback);
    }

    /**
     * Add a callback to modify the Eloquent query before loading models.
     *
     * @param  Closure(Builder, array): mixed  $callback  Return value is ignored.
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
     * @param  Closure(Collection): Collection  $callback
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
        $this->validatedRequestedRootFields = array_values(array_unique($requestedFields));
        $this->safeRootHiddenFields = [];

        // An empty set means no sparse fieldset narrows this resource. The base
        // package resolves a `fields` parameter that selects nothing for this
        // resource to [] rather than null, so without this guard the select below
        // would collapse to the key alone and hide every other attribute.
        // EloquentQueryWizard::applyFields() makes the same distinction.
        if (empty($requestedFields) || $requestedFields === ['*']) {
            return;
        }

        $fields = $this->applySafeRootFieldRequirements($requestedFields);
        $this->safeRootHiddenFields = array_values(array_diff($fields, $requestedFields));

        /** @var Model $model */
        $model = new $this->modelClass;
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
        return $this->resolveDefaultResourceKey($this->modelClass);
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
                    $results->each(fn (Model $model) => $model->setHidden($newHidden));
                }
            }
        }
    }

    protected function applyFilter(FilterInterface $filter, mixed $preparedValue): void
    {
        /** @var SearchBuilder $result */
        $result = $filter->apply($this->subject, $preparedValue);
        $this->subject = $result;
    }

    /**
     * Groups are containers: their own name is not a valid request key, their
     * leaves are.
     *
     * Names are normalized here because they are matched against the request keys
     * parsed by the parameters manager, which are normalized too.
     *
     * @param  array<string, FilterInterface>  $filters
     * @return array<int, string>
     */
    protected function resolveAllowedFilterNames(array $filters): array
    {
        $names = [];

        foreach ($filters as $filter) {
            if ($filter instanceof GroupInterface) {
                foreach ($filter->getChildFilterNames() as $childName) {
                    $names[] = $this->normalizePublicPath($childName);
                }

                continue;
            }

            $names[] = $this->normalizePublicPath($filter->getName());
        }

        return array_values(array_unique($names));
    }

    /**
     * A filter name may be registered both at root level and inside a group.
     * The group owns the request key in that case, so the root-level filter must
     * not be applied a second time.
     *
     * The comparison is on normalized names on both sides - getEffectiveFilters()
     * already keys by the normalized name, and the group children are normalized
     * below. That is deliberate: what makes the root-level filter redundant is
     * that it reads the same request key, and two names differing only in case
     * collapse to one key once normalization is on.
     *
     * @param  array<string, FilterInterface>  $filters
     * @return array<string, true>
     */
    protected function resolveShadowedFilterNames(array $filters): array
    {
        $groupChildNames = [];

        foreach ($filters as $filter) {
            if (! $filter instanceof GroupInterface) {
                continue;
            }

            foreach ($filter->getChildFilterNames() as $childName) {
                $groupChildNames[$this->normalizePublicPath($childName)] = true;
            }
        }

        if ($groupChildNames === []) {
            return [];
        }

        $shadowed = [];

        foreach ($filters as $name => $filter) {
            if (! $filter instanceof GroupInterface && isset($groupChildNames[$name])) {
                $shadowed[$name] = true;
            }
        }

        return $shadowed;
    }

    /**
     * A group resolves to the map of its leaves' prepared values.
     *
     * The map is keyed by the raw child name because that is what
     * AbstractElasticGroup::applyChildrenToQuery() looks the values up by.
     * Normalization only applies to the name set used for request validation.
     */
    protected function resolvePreparedFilterValue(FilterInterface $filter): mixed
    {
        if (! $filter instanceof GroupInterface) {
            return parent::resolvePreparedFilterValue($filter);
        }

        $childValues = [];

        foreach ($this->collectGroupLeafFilters($filter) as $child) {
            // Dispatch through $this, not parent::, so a subclass that customises
            // value resolution sees leaves nested in a group as well as root-level
            // filters. collectGroupLeafFilters() has already flattened away every
            // group, so this cannot recurse back into this branch.
            $preparedValue = $this->resolvePreparedFilterValue($child);

            if ($preparedValue === null) {
                continue;
            }

            $childValues[$child->getName()] = $preparedValue;
        }

        return $childValues === [] ? null : $childValues;
    }

    /**
     * Flatten a group tree down to its leaf filters.
     *
     * @return array<int, FilterInterface>
     */
    protected function collectGroupLeafFilters(GroupInterface $group): array
    {
        $leaves = [];

        foreach ($group->getChildren() as $child) {
            if ($child instanceof GroupInterface) {
                foreach ($this->collectGroupLeafFilters($child) as $nestedChild) {
                    $leaves[] = $nestedChild;
                }

                continue;
            }

            $leaves[] = $child;
        }

        return $leaves;
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
        $model = new $this->modelClass;
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
            $safeRelationSelectColumnsByPath = [];
            foreach ($relationshipPaths as $relationPath) {
                $columns = $this->getSafeRelationSelectColumns($relationPath);
                if ($columns !== null) {
                    $safeRelationSelectColumnsByPath[$relationPath] = $columns;
                }
            }

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

    protected function prepareBuild(): void
    {
        $this->applySearchBuilderModifiers();
    }

    protected function finalizeBuild(): void
    {
        $this->finalizeSubject();
    }

    protected function invalidateBuild(): void
    {
        if ($this->proxyModified) {
            throw new \LogicException(
                'Cannot modify query wizard configuration after calling query builder methods. '
                .'Call all configuration methods (allowedFilters, allowedSorts, etc.) before query builder methods.'
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
     * @param  Closure(Builder, SearchResult): mixed  $callback
     */
    protected function addBuildQueryModifier(Closure $callback): void
    {
        $this->buildQueryModifiers[] = $callback;
    }

    /**
     * @param  Closure(SearchBuilder): mixed  $callback
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
     * @param  array<int, mixed>  $arguments
     */
    public function __call(string $name, array $arguments): mixed
    {
        if ($this->isSearchBuilderFluentMethod($name)) {
            return $this->queueSearchBuilderMutation(
                fn (SearchBuilder $builder) => $builder->{$name}(...$arguments)
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

    /**
     * Only the taint flag is reset: a fresh clone has not been modified through
     * the search-builder proxy yet.
     *
     * The derived post-processing state (append tree, relation field tree, root
     * field masks, build-query modifiers) is left in place - it describes the
     * subject this clone carries over, and only build() can rebuild it.
     */
    public function __clone(): void
    {
        parent::__clone();
        $this->proxyModified = false;
    }
}
