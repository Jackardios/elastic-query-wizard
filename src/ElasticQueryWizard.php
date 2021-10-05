<?php

namespace Jackardios\ElasticQueryWizard;

use ElasticScoutDriverPlus\Builders\SearchRequestBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Jackardios\QueryWizard\Abstracts\AbstractQueryWizard;
use Jackardios\QueryWizard\Concerns\HandlesAppends;
use Jackardios\QueryWizard\Concerns\HandlesFields;
use Jackardios\QueryWizard\Concerns\HandlesFilters;
use Jackardios\QueryWizard\Concerns\HandlesIncludes;
use Jackardios\QueryWizard\Concerns\HandlesSorts;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\AbstractEloquentInclude;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\IncludedCount;
use Jackardios\QueryWizard\Handlers\Eloquent\Includes\IncludedRelationship;
use Jackardios\ElasticQueryWizard\Handlers\Filters\TermFilter;
use Jackardios\ElasticQueryWizard\Handlers\Sorts\SortsByField;
use Jackardios\ElasticQueryWizard\Handlers\ElasticQueryHandler;

/**
 * @mixin SearchRequestBuilder
 * @property ElasticQueryHandler $queryHandler
 * @method ElasticQueryHandler getHandler()
 * @method static ElasticQueryWizard for(Model|string $subject, \Illuminate\Http\Request|null $request = null)
 */
class ElasticQueryWizard extends AbstractQueryWizard
{
    use HandlesAppends;
    use HandlesFields;
    use HandlesFilters;
    use HandlesIncludes;
    use HandlesSorts;

    protected string $queryHandlerClass = ElasticQueryHandler::class;

    protected function defaultFieldsKey(): string
    {
        return $this->queryHandler->getSubject()->getModel()->getTable();
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
        $this->queryHandler->addEloquentQueryCallback($callback);

        return $this;
    }

    public function makeDefaultFilterHandler(string $filterName): TermFilter
    {
        return new TermFilter($filterName);
    }

    /**
     * @param string $includeName
     * @return IncludedRelationship|IncludedCount
     */
    public function makeDefaultIncludeHandler(string $includeName): AbstractEloquentInclude
    {
        $countSuffix = config('query-wizard.count_suffix');
        if (Str::endsWith($includeName, $countSuffix)) {
            $relation = Str::before($includeName, $countSuffix);
            return new IncludedCount($relation, $includeName);
        }
        return new IncludedRelationship($includeName);
    }

    public function makeDefaultSortHandler(string $sortName): SortsByField
    {
        return new SortsByField($sortName);
    }
}
