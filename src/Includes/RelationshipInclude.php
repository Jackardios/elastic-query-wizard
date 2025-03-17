<?php

namespace Jackardios\ElasticQueryWizard\Includes;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Jackardios\ElasticQueryWizard\ElasticInclude;

class RelationshipInclude extends ElasticInclude
{
    /** {@inheritdoc} */
    public function handle($queryWizard, $queryBuilder): void
    {
        $relationNames = collect(explode('.', $this->getInclude()));

        $eagerLoads = $queryBuilder->getEagerLoads();
        $withs = $relationNames
            ->mapWithKeys(function ($table, $key) use ($queryWizard, $relationNames, $eagerLoads) {
                $fullRelationName = $relationNames->slice(0, $key + 1)->implode('.');

                if (array_key_exists($fullRelationName, $eagerLoads)) {
                    return [];
                }

                $fields = method_exists($queryWizard, 'getFieldsByKey') ? $queryWizard->getFieldsByKey($fullRelationName) : null;

                if (empty($fields)) {
                    return [$fullRelationName => static function() {}];
                }

                return [$fullRelationName => function ($query) use ($fields) {
                    $query->select($query->qualifyColumns($fields));
                }];
            })
            ->filter()
            ->toArray();

        $queryBuilder->setEagerLoads(array_merge($eagerLoads, $withs));
    }

    protected function getIndividualRelationshipPathsFromInclude(string $include): Collection
    {
        return collect(explode('.', $include))
            ->reduce(function (Collection $includes, string $relationship) {
                if ($includes->isEmpty()) {
                    return $includes->push($relationship);
                }

                return $includes->push("{$includes->last()}.{$relationship}");
            }, collect());
    }

    public function createExtra(): array
    {
        return $this->getIndividualRelationshipPathsFromInclude($this->getInclude())
            ->map(function ($include) {
                if (empty($include)) {
                    return [];
                }

                $includes = [];

                if ($this->getInclude() !== $include) {
                    $includes[] = new static($include);
                }

                if (! Str::contains($include, '.')) {
                    $includes[] = new CountInclude($include);
                }

                return $includes;
            })
            ->flatten()
            ->toArray();
    }
}
