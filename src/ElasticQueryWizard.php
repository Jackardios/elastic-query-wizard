<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Jackardios\ElasticQueryWizard\Exceptions\FilterNameConflict;
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
 *
 * The wizard forwards the public methods of SearchBuilder. The fluent ones, and when() and unless(), return the
 * wizard: it applies them when it builds, or at once after the build. The others build it first and return the
 * builder's result.
 *
 * @method $this query(\Jackardios\EsScoutDriver\Query\QueryInterface|\Closure|array<mixed> $query)
 * @method $this clearQuery()
 * @method $this must(\Jackardios\EsScoutDriver\Query\QueryInterface|\Closure|array<mixed> ...$queries)
 * @method $this mustNot(\Jackardios\EsScoutDriver\Query\QueryInterface|\Closure|array<mixed> ...$queries)
 * @method $this should(\Jackardios\EsScoutDriver\Query\QueryInterface|\Closure|array<mixed> ...$queries)
 * @method $this filter(\Jackardios\EsScoutDriver\Query\QueryInterface|\Closure|array<mixed> ...$queries)
 * @method bool hasBoolQuery()
 * @method ?\Jackardios\EsScoutDriver\Query\Compound\BoolQuery getBoolQuery()
 * @method $this clearBoolQuery()
 * @method $this softDelete(\Jackardios\EsScoutDriver\Enums\SoftDeleteMode $mode)
 * @method $this withTrashed()
 * @method $this onlyTrashed()
 * @method $this excludeTrashed()
 * @method \Jackardios\EsScoutDriver\Enums\SoftDeleteMode getSoftDeleteMode()
 * @method $this highlightRaw(array<mixed> $highlight)
 * @method $this highlight(string $field, array<mixed> $parameters = [], ?int $fragmentSize = null, ?int $numberOfFragments = null, ?array<mixed> $preTags = null, ?array<mixed> $postTags = null)
 * @method $this highlightGlobal(?int $fragmentSize = null, ?int $numberOfFragments = null, ?array<mixed> $preTags = null, ?array<mixed> $postTags = null, ?string $type = null, ?string $boundaryScanner = null, ?string $encoder = null)
 * @method $this clearHighlight()
 * @method $this sortRaw(array<mixed> $sort)
 * @method $this sort(\Jackardios\EsScoutDriver\Sort\SortInterface|string $field, \Jackardios\EsScoutDriver\Enums\SortOrder|string|null $direction = null, string|int|float|bool|null $missing = null, ?string $mode = null, ?string $unmappedType = null)
 * @method $this clearSort()
 * @method $this rescoreRaw(array<mixed> $rescore)
 * @method $this rescore(\Jackardios\EsScoutDriver\Query\QueryInterface|\Closure|array<mixed> $query, ?int $windowSize = null, ?float $queryWeight = null, ?float $rescoreQueryWeight = null)
 * @method $this clearRescore()
 * @method $this from(int $from)
 * @method $this size(int $size)
 * @method $this suggestRaw(array<mixed> $suggest)
 * @method $this suggest(string $name, array<mixed> $definition)
 * @method $this clearSuggest()
 * @method $this sourceRaw(array<mixed>|string|bool $source)
 * @method $this withoutSource()
 * @method $this source(array<mixed> $includes, ?array<mixed> $excludes = null)
 * @method $this clearSource()
 * @method $this collapseRaw(array<mixed> $collapse)
 * @method $this collapse(string $field)
 * @method $this clearCollapse()
 * @method $this aggregateRaw(array<mixed> $aggregations)
 * @method $this aggregate(string $name, \Jackardios\EsScoutDriver\Aggregations\AggregationInterface|array<mixed> $definition)
 * @method $this clearAggregations()
 * @method $this postFilter(\Jackardios\EsScoutDriver\Query\QueryInterface|\Closure|array<mixed> $query)
 * @method $this clearPostFilter()
 * @method $this trackTotalHits(int|bool $trackTotalHits)
 * @method $this trackScores(bool $trackScores)
 * @method $this minScore(float $minScore)
 * @method $this searchType(string $searchType)
 * @method $this preference(string $preference)
 * @method $this pointInTime(string $id, ?string $keepAlive = null)
 * @method $this clearPointInTime()
 * @method $this searchAfter(array<mixed> $searchAfter)
 * @method $this clearSearchAfter()
 * @method $this routing(array<mixed>|string|int|null $routing)
 * @method $this clearRouting()
 * @method $this explain(bool $explain)
 * @method $this terminateAfter(int $terminateAfter)
 * @method $this requestCache(bool $requestCache)
 * @method $this timeout(string $timeout)
 * @method $this storedFields(array<mixed> $fields)
 * @method $this docvalueFields(array<mixed> $fields)
 * @method $this version(bool $version = true)
 * @method $this scriptFields(array<mixed> $scriptFields)
 * @method $this runtimeMappings(array<mixed> $runtimeMappings)
 * @method $this knn(string $field, array<mixed> $queryVector, int $k, ?int $numCandidates = null, ?float $similarity = null, \Jackardios\EsScoutDriver\Query\QueryInterface|array<mixed>|null $filter = null)
 * @method $this knnRaw(array<mixed> $knn)
 * @method $this clearKnn()
 * @method ?array<mixed> getKnn()
 * @method $this join(string $modelClass, ?float $boost = null)
 * @method $this clearIndicesBoost()
 * @method $this with(array<mixed> $relations, ?string $modelClass = null)
 * @method $this clearQueryModifiers(?string $modelClass = null)
 * @method $this clearModelModifiers(?string $modelClass = null)
 * @method ?array<mixed> getQuery()
 * @method array<mixed> getSort()
 * @method ?int getFrom()
 * @method ?int getSize()
 * @method array<mixed>|string|bool|null getSource()
 * @method array<mixed> getHighlight()
 * @method array<mixed> getAggregations()
 * @method ?array<mixed> getPostFilter()
 * @method array<mixed> getRescore()
 * @method array<mixed> getSuggest()
 * @method array<mixed> getCollapse()
 * @method int|bool|null getTrackTotalHits()
 * @method ?bool getTrackScores()
 * @method ?float getMinScore()
 * @method ?string getSearchType()
 * @method ?string getPreference()
 * @method ?array<mixed> getPointInTime()
 * @method ?array<mixed> getSearchAfter()
 * @method ?array<mixed> getRouting()
 * @method ?bool getExplain()
 * @method ?string getTimeout()
 * @method array<mixed> getIndexNames()
 * @method $this clearAll()
 * @method array<mixed> buildParams()
 * @method \Jackardios\EsScoutDriver\Search\SearchResult execute()
 * @method array<mixed> raw()
 * @method ?\Jackardios\EsScoutDriver\Search\Hit first()
 * @method \Jackardios\EsScoutDriver\Search\Hit firstOrFail()
 * @method int count()
 * @method array<mixed> deleteByQuery()
 * @method array<mixed> updateByQuery(array<mixed> $script)
 * @method \Jackardios\EsScoutDriver\Engine\EngineInterface getEngine()
 * @method string toJson(int $options = 128)
 * @method array<mixed> toArray()
 * @method \Jackardios\EsScoutDriver\Search\SearchCursor cursor(int $chunkSize = 1000, string $keepAlive = '5m')
 * @method void chunk(int $chunkSize, callable $callback)
 * @method $this when(mixed $value, callable $callback, ?callable $default = null)
 * @method $this unless(mixed $value, callable $callback, ?callable $default = null)
 *
 * @extends BaseQueryWizard<SearchBuilder>
 *
 * @phpstan-consistent-constructor
 */
class ElasticQueryWizard extends BaseQueryWizard
{
    /** @var SearchBuilder */
    protected mixed $subject;

    /**
     * @var array<int, callable(Builder<Model>, array<string, mixed>): mixed>
     *
     * @internal
     */
    protected array $queryModifiers = [];

    /**
     * @var array<int, callable(Collection<int, Model>): Collection<int, Model>>
     *
     * @internal
     */
    protected array $modelModifiers = [];

    /**
     * @var array<int, Closure(SearchBuilder): mixed>
     *
     * @internal
     */
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

    /** @var array<string, bool|null> */
    private static array $searchBuilderFluentMethods = [];

    /**
     * Set when the built search builder is changed through the proxy. A clone
     * keeps it: reconfiguring rebuilds from the original subject and would drop
     * those changes.
     */
    private bool $proxyModified = false;

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
            throw new \InvalidArgumentException(sprintf('`%s` is not a model using the `Jackardios\EsScoutDriver\Searchable` trait.', $subject));
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

        return new static($modelClass, null, null, $resolvedSchema);
    }

    public function getSubject(): SearchBuilder
    {
        return $this->subject;
    }

    /**
     * The root bool query of the built search; the wizard builds first. A later
     * configuration change throws a `LogicException`, since the rebuild would
     * drop what was changed here; use `tapSearchBuilder()` for a change that
     * survives rebuilds.
     */
    public function boolQuery(): BoolQuery
    {
        $this->build();
        $this->proxyModified = true;

        return $this->subject->boolQuery();
    }

    /**
     * Apply custom SearchBuilder mutations declaratively (before/after build).
     *
     * @param  callable(SearchBuilder): mixed  $callback  Return value is ignored.
     */
    public function tapSearchBuilder(callable $callback): static
    {
        return $this->queueSearchBuilderMutation($callback(...));
    }

    /**
     * Add a callback to modify the Eloquent query before loading models.
     *
     * @param  callable(Builder<Model>, array<string, mixed>): mixed  $callback  Return value is ignored.
     */
    public function modifyQuery(callable $callback): static
    {
        $this->assertBuildCallbackCanBeAdded('modifyQuery');
        $this->queryModifiers[] = $callback;

        return $this;
    }

    /**
     * Add a callback to modify the loaded Eloquent collection.
     *
     * @param  callable(Collection<int, Model>): Collection<int, Model>  $callback
     */
    public function modifyModels(callable $callback): static
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
     * @throws FilterNameConflict When a leaf is in more than one group,
     *                            where one request value would apply in each,
     *                            or a shadowed root filter has a default
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
            throw FilterNameConflict::leavesInSeveralGroups($sharedLeaves);
        }

        $shadowed = [];

        foreach ($filters as $name => $filter) {
            if (! $filter instanceof GroupInterface && isset($groupsByLeaf[$name])) {
                if ($filter->getDefault() !== null) {
                    throw FilterNameConflict::shadowedDefault($filter->getName());
                }

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
     *
     * @internal
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

    protected function prepareBuild(): void
    {
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
                'The wizard cannot be reconfigured after its built search was changed through the wizard: the rebuild '
                .'would drop the change. Configure the wizard first, or change the search in tapSearchBuilder().'
            );
        }

        parent::invalidateBuild();
    }

    /** @internal */
    protected function applySearchBuilderModifiers(): void
    {
        foreach ($this->searchBuilderModifiers as $callback) {
            $callback($this->subject);
        }
    }

    /**
     * @param  Closure(SearchBuilder): mixed  $callback
     *
     * @internal
     */
    protected function queueSearchBuilderMutation(Closure $callback): static
    {
        if ($this->isBuilt()) {
            $this->proxyModified = true;
            $callback($this->subject);

            return $this;
        }

        $this->invalidateBuild();
        $this->searchBuilderModifiers[] = $callback;

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
     * Forward a call to the search builder. A method that returns the builder,
     * and when() or unless() with a callback, is applied at the build, or to the
     * built search once the wizard is built. Any other method or macro builds
     * the wizard first and runs on the built search; the result is returned,
     * or the wizard in place of the builder.
     *
     * @param  array<int, mixed>  $arguments
     *
     * @throws \BadMethodCallException When the search builder has no such public method or macro,
     *                                 or when() or unless() gets no callback
     */
    public function __call(string $name, array $arguments): mixed
    {
        $isFluent = $this->isSearchBuilderFluentMethod($name);

        if ($isFluent === null && ! SearchBuilder::hasMacro($name)) {
            throw new \BadMethodCallException(sprintf('Call to undefined method %s::%s()', static::class, $name));
        }

        $isConditional = $name === 'when' || $name === 'unless';

        if ($isConditional && count($arguments) < 2) {
            throw new \BadMethodCallException(sprintf(
                'Pass a callback to %s::%s(): the wizard applies it to the search builder when it builds.',
                static::class,
                $name
            ));
        }

        if ($isFluent === true || $isConditional) {
            return $this->queueSearchBuilderMutation(
                fn (SearchBuilder $builder) => $builder->{$name}(...$arguments)
            );
        }

        $this->build();
        $result = $this->subject->$name(...$arguments);

        if ($result === $this->subject || $result instanceof BoolQuery) {
            $this->proxyModified = true;
        }

        return $result === $this->subject ? $this : $result;
    }

    /**
     * The last result a page may reach, from `elastic-query-wizard.max_result_window`
     * (Elasticsearch's default `index.max_result_window`); null lifts the limit.
     *
     * @throws \InvalidArgumentException When the value is not a positive integer, a string of digits holding one, or null
     */
    private function maxResultWindow(): ?int
    {
        $value = config('elastic-query-wizard.max_result_window', self::DEFAULT_MAX_RESULT_WINDOW);

        if ($value === null) {
            return null;
        }

        if (is_string($value) && preg_match('/^\d+\z/', $value) === 1) {
            $value = filter_var($value, FILTER_VALIDATE_INT);
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        throw new \InvalidArgumentException(
            'Config `elastic-query-wizard.max_result_window` must be a positive integer or null.'
        );
    }

    /**
     * Whether the public SearchBuilder method returns the builder; null when
     * SearchBuilder has no public method of that name.
     */
    private function isSearchBuilderFluentMethod(string $name): ?bool
    {
        if (array_key_exists($name, self::$searchBuilderFluentMethods)) {
            return self::$searchBuilderFluentMethods[$name];
        }

        $isFluent = null;

        if (method_exists(SearchBuilder::class, $name) && ($method = new \ReflectionMethod(SearchBuilder::class, $name))->isPublic()) {
            $returnType = $method->getReturnType();
            $types = $returnType instanceof \ReflectionUnionType ? $returnType->getTypes() : [$returnType];
            $isFluent = false;

            foreach ($types as $type) {
                if ($type instanceof \ReflectionNamedType && $this->isFluentNamedReturnType($type)) {
                    $isFluent = true;

                    break;
                }
            }
        }

        return self::$searchBuilderFluentMethods[$name] = $isFluent;
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
                '%s() cannot be called after build(): register the model callbacks before the build.',
                $methodName
            )
        );
    }
}
