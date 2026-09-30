<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Jackardios\ElasticQueryWizard\Exceptions\FilterNameConflictException;
use Jackardios\ElasticQueryWizard\Exceptions\MaxResultWindowExceeded;
use Jackardios\ElasticQueryWizard\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Groups\GroupInterface;
use Jackardios\ElasticQueryWizard\Includes\AbstractElasticInclude;
use Jackardios\ElasticQueryWizard\Sorts\FieldSort;
use Jackardios\EsScoutDriver\Query\Compound\BoolQuery;
use Jackardios\EsScoutDriver\Search\Paginator;
use Jackardios\EsScoutDriver\Search\SearchBuilder;
use Jackardios\EsScoutDriver\Search\SearchResult;
use Jackardios\QueryWizard\BaseQueryWizard;
use Jackardios\QueryWizard\Config\QueryWizardConfig;
use Jackardios\QueryWizard\Contracts\FilterInterface;
use Jackardios\QueryWizard\Contracts\IncludeInterface;
use Jackardios\QueryWizard\Contracts\SortInterface;
use Jackardios\QueryWizard\Eloquent\EloquentShape;
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
 * - `ElasticSort::random()` sends `field: _seq_no` with a seed, which ES 8.x requires
 * - Don't use histogram aggregation on boolean fields (use terms aggregation instead)
 *
 * Query execution methods (delegated to SearchBuilder):
 *
 * @method SearchResult execute() Execute query and get full SearchResult (hits, models, documents, aggregations, suggestions)
 * @method \Jackardios\EsScoutDriver\Search\Hit|null first() Get first hit (use ->model() to get Model)
 * @method \Jackardios\EsScoutDriver\Search\Hit firstOrFail() Get first hit or throw ModelNotFoundException
 * @method int count() Get total count without loading models
 * @method array<string, mixed> raw() Get raw Elasticsearch response array
 *
 * @extends BaseQueryWizard<SearchBuilder>
 *
 * @phpstan-consistent-constructor
 *
 * @mixin SearchBuilder
 */
class ElasticQueryWizard extends BaseQueryWizard
{
    /** @var SearchBuilder */
    protected mixed $subject;

    /** @var array<int, Closure(Builder<Model>, array<string, mixed>): mixed> */
    protected array $queryModifiers = [];

    /** @var array<int, Closure(Collection<int, Model>): Collection<int, Model>> */
    protected array $modelModifiers = [];

    /** @var array<int, Closure(SearchBuilder): mixed> */
    protected array $searchBuilderModifiers = [];

    /** @var class-string<Model> */
    protected string $modelClass;

    /**
     * The includes of the build in progress, by requested name.
     *
     * @var array<string, IncludeInterface>
     */
    private array $shapeIncludes = [];

    /** @var array<string>|null */
    private ?array $shapeRootFields = null;

    private ?EloquentShape $shape = null;

    private const DEFAULT_MAX_RESULT_WINDOW = 10000;

    /** @var array<string, bool> */
    private static array $searchBuilderFluentMethods = [];

    /**
     * Set when the built search builder is changed through the proxy. A clone
     * keeps it: reconfiguring rebuilds from the original subject and would drop
     * those changes.
     */
    private bool $proxyModified = false;

    /**
     * Set when boolQuery() is called before the build: the change lives on the
     * subject, which a rebuild after a configuration change replaces.
     */
    private bool $boolQueryChangedBeforeBuild = false;

    /**
     * Set when a build fails after boolQuery() was called: the rollback
     * restores the original subject, without the change.
     */
    private bool $boolQueryChangeRolledBack = false;

    /**
     * @param  class-string<Model>  $subject  A model class using the `Jackardios\EsScoutDriver\Searchable` trait
     *
     * @throws \InvalidArgumentException When the class is not a searchable model, or the schema describes another model
     */
    public function __construct(
        string $subject,
        ?QueryParametersManager $parameters = null,
        ?QueryWizardConfig $config = null,
        ?ResourceSchemaInterface $schema = null,
    ) {
        if (! (is_subclass_of($subject, Model::class) && method_exists($subject, 'searchQuery'))) {
            throw new \InvalidArgumentException('$subject must be a model that uses `Jackardios\EsScoutDriver\Searchable` trait');
        }

        $this->modelClass = $subject;

        parent::__construct($subject::searchQuery(), $parameters, $config, $schema);
    }

    /**
     * Search a model class. A model instance is not accepted: the search would
     * cover the whole index, not that model.
     *
     * @param  class-string<Model>  $subject
     */
    public static function for(string $subject, ?QueryParametersManager $parameters = null): static
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

    /**
     * The root bool query of the search. A configuration change after the build
     * throws a `LogicException`, since the rebuild would drop what was changed
     * here, and so does a build after one that failed; use `tapSearchBuilder()`
     * for a change that survives rebuilds.
     */
    public function boolQuery(): BoolQuery
    {
        if ($this->isBuilt()) {
            $this->proxyModified = true;
        } else {
            $this->boolQueryChangedBeforeBuild = true;
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
     * @param  Closure(Builder<Model>, array<string, mixed>): mixed  $callback  Return value is ignored.
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
     * @param  Closure(Collection<int, Model>): Collection<int, Model>  $callback
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
        $config = $this->getConfig();

        return RelationshipInclude::fromString($name, $config->getCountSuffix(), $config->getExistsSuffix());
    }

    protected function applyFields(array $fields): void
    {
        $this->shapeRootFields = $fields;
    }

    public function getResourceKey(): string
    {
        return $this->resolveDefaultResourceKey($this->modelClass);
    }

    /**
     * The model the wizard searches, for wildcard appends and hidden fields.
     */
    protected function resourceModel(): Model
    {
        return new $this->modelClass;
    }

    /**
     * Apply post-processing to externally-loaded results.
     *
     * Use this method when executing queries outside the wizard (e.g., via getSubject())
     * to apply sparse fieldsets and appends to loaded models.
     *
     * A lazy collection is not read: a new one is returned that post-processes
     * each model as it is read.
     *
     * @template T of Model|\Traversable<mixed>|array<mixed>
     *
     * @param  T  $results  Single model, collection, lazy collection, or iterable of models
     * @return (T is LazyCollection<array-key, mixed> ? LazyCollection<array-key, mixed> : T) The same results with post-processing applied, or a new lazy collection for a lazy collection
     *
     * @throws \InvalidArgumentException For a generator when there is post-processing to apply, which would use it up
     */
    public function applyPostProcessingTo(mixed $results): mixed
    {
        $this->build();

        return $this->shape === null ? $results : $this->shape->postProcess($results);
    }

    protected function applyFilter(FilterInterface $filter, mixed $preparedValue): void
    {
        /** @var SearchBuilder $result */
        $result = $filter->apply($this->subject, $preparedValue);
        $this->subject = $result;
    }

    /**
     * Groups are containers: their own name is not a valid request key, their
     * leaves are. The core removes the leaves that disallowedFilters() names.
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
     *
     * @throws FilterNameConflictException When a leaf is in more than one group,
     *                                     where one request value would apply in each
     */
    protected function resolveShadowedFilterNames(array $filters): array
    {
        $groupsByLeaf = [];

        foreach ($filters as $name => $filter) {
            if (! $filter instanceof GroupInterface) {
                continue;
            }

            foreach (array_unique(array_map($this->normalizePublicPath(...), $filter->getChildFilterNames())) as $leafName) {
                $groupsByLeaf[$leafName][] = $name;
            }
        }

        $sharedLeaves = array_keys(array_filter($groupsByLeaf, static fn (array $groups): bool => count($groups) > 1));

        if ($sharedLeaves !== []) {
            throw FilterNameConflictException::leavesInSeveralGroups($sharedLeaves);
        }

        $shadowed = [];

        foreach ($filters as $name => $filter) {
            if (! $filter instanceof GroupInterface && isset($groupsByLeaf[$name])) {
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
     * Includes load with the models, so they are collected here and applied
     * by the shape when the search results are resolved to models.
     *
     * @param  array<int, string>  $validRequestedIncludes
     * @param  array<string, IncludeInterface>  $includesIndex
     */
    protected function applyValidatedIncludes(array $validRequestedIncludes, array $includesIndex): void
    {
        foreach ($validRequestedIncludes as $includeName) {
            $this->shapeIncludes[$includeName] = $includesIndex[$includeName];
        }
    }

    protected function rollbackFailedBuild(): void
    {
        $this->boolQueryChangeRolledBack = $this->boolQueryChangedBeforeBuild;

        parent::rollbackFailedBuild();
    }

    /**
     * @throws \LogicException When an earlier build failed after boolQuery() was
     *                         called, whose change the rollback dropped
     */
    protected function prepareBuild(): void
    {
        if ($this->boolQueryChangeRolledBack) {
            throw new \LogicException(
                'Cannot rebuild a wizard whose bool query was changed before a failed build: the rollback dropped the '
                .'change. Change the bool query in tapSearchBuilder(), which runs on every build.'
            );
        }

        $this->shapeIncludes = [];
        $this->shapeRootFields = null;
        $this->shape = null;
        $this->applySearchBuilderModifiers();
    }

    /**
     * Validate the Eloquent side of the request and register the callbacks
     * that shape the model query and the loaded models.
     *
     * The callbacks capture the shape, not the wizard, so a clone or a later
     * reconfiguration can't change a search that was already built. The shape
     * runs after the developer's modifyQuery() callbacks, so it selects the
     * keys of the eager loads they register.
     */
    protected function finalizeBuild(): void
    {
        $model = $this->resourceModel();
        $scoutKeyName = method_exists($model, 'getScoutKeyName') ? $model->getScoutKeyName() : null;
        $shape = $this->shape = $this->resolveEloquentShape(
            $this->shapeIncludes,
            $this->shapeRootFields,
            array_values(array_unique(array_filter([$model->getKeyName(), $scoutKeyName], is_string(...)))),
        );
        $elasticIncludes = array_values(array_filter(
            $this->shapeIncludes,
            static fn (IncludeInterface $include): bool => $include instanceof AbstractElasticInclude
        ));
        $queryModifiers = $this->queryModifiers;
        $modelModifiers = $this->modelModifiers;

        /**
         * @param  Builder<Model>  $builder
         * @param  array<string, mixed>  $rawResult
         */
        $modifyQuery = static function (Builder $builder, array $rawResult) use ($queryModifiers, $elasticIncludes, $shape): void {
            foreach ($queryModifiers as $callback) {
                $callback($builder, $rawResult);
            }

            if ($elasticIncludes !== []) {
                $searchResult = new SearchResult($rawResult);

                foreach ($elasticIncludes as $include) {
                    $include->setSearchResult($searchResult);
                }
            }

            /** @var Builder<Model> $builder */
            $shape->applyTo($builder);
        };

        /**
         * @param  Collection<int, Model>  $collection
         * @return Collection<int, Model>
         */
        $modifyModels = static function (Collection $collection) use ($modelModifiers, $shape): Collection {
            foreach ($modelModifiers as $callback) {
                $collection = $callback($collection);
            }

            return $shape->postProcess($collection);
        };

        $this->subject
            ->modifyQuery($modifyQuery, $this->modelClass)
            ->modifyModels($modifyModels, $this->modelClass);
    }

    protected function invalidateBuild(): void
    {
        if ($this->proxyModified) {
            throw new \LogicException(
                'Cannot modify query wizard configuration after calling query builder methods. '
                .'Call all configuration methods (allowedFilters, allowedSorts, etc.) before query builder methods.'
            );
        }

        if ($this->boolQueryChangedBeforeBuild && $this->isBuilt()) {
            throw new \LogicException(
                'Cannot modify query wizard configuration of a built wizard whose bool query was changed: the rebuild '
                .'would drop the change. Change the bool query in tapSearchBuilder(), which runs on every build.'
            );
        }

        parent::invalidateBuild();
    }

    protected function applySearchBuilderModifiers(): void
    {
        foreach ($this->searchBuilderModifiers as $callback) {
            $callback($this->subject);
        }
    }

    /**
     * @param  Closure(SearchBuilder): mixed  $callback
     */
    protected function queueSearchBuilderMutation(Closure $callback): static
    {
        if (! $this->isBuilt()) {
            $this->invalidateBuild();
        }

        $this->searchBuilderModifiers[] = $callback;

        if ($this->isBuilt()) {
            $this->proxyModified = true;
            $callback($this->subject);
        }

        return $this;
    }

    /**
     * Paginate the results; call `withModels()` or `withDocuments()` on the paginator.
     *
     * @throws MaxResultWindowExceeded When the page ends past `elastic-query-wizard.max_result_window`
     */
    public function paginate(int $perPage = 15, string $pageName = 'page', ?int $page = null): Paginator
    {
        $page ??= Paginator::resolveCurrentPage($pageName);
        $maxResultWindow = $this->maxResultWindow();

        if ($maxResultWindow !== null && $perPage >= 1 && $page > intdiv($maxResultWindow, $perPage)) {
            throw new MaxResultWindowExceeded($page, $perPage, $maxResultWindow, $pageName);
        }

        $this->build();

        return $this->subject->paginate($perPage, $pageName, $page);
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

        if ($result === $this->subject || $result instanceof BoolQuery) {
            $this->proxyModified = true;

            return $this;
        }

        return $result;
    }

    /**
     * The last result a page may reach, from `elastic-query-wizard.max_result_window`
     * (Elasticsearch's default `index.max_result_window`); null lifts the limit.
     *
     * @throws \InvalidArgumentException When the value is not a positive integer or null
     */
    private function maxResultWindow(): ?int
    {
        $value = config('elastic-query-wizard.max_result_window', self::DEFAULT_MAX_RESULT_WINDOW);

        if ($value !== null && (! is_int($value) || $value < 1)) {
            throw new \InvalidArgumentException(
                'Config `elastic-query-wizard.max_result_window` must be a positive integer or null.'
            );
        }

        return $value;
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
        if (! $this->isBuilt()) {
            $this->invalidateBuild();

            return;
        }

        throw new \LogicException(
            sprintf(
                'Cannot call %s() after build(). Register query/model callbacks before build().',
                $methodName
            )
        );
    }
}
