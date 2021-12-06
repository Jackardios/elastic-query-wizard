<?php

namespace Jackardios\ElasticQueryWizard\Handlers\Includes;

use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class RelationshipInclude extends AbstractElasticInclude
{
    /** {@inheritdoc} */
    public function handle($queryHandler, $queryBuilder): void
    {
        $relatedTables = collect(explode('.', $this->getInclude()));

        $withs = $relatedTables
            ->mapWithKeys(function ($table, $key) use ($queryHandler, $relatedTables) {
                $fullRelationName = $relatedTables->slice(0, $key + 1)->implode('.');

                $key = Str::plural(Str::snake($fullRelationName));
                $fields = $queryHandler->getWizard()->getFieldsByKey($key);

                if (empty($fields)) {
                    return [$fullRelationName];
                }

                return [$fullRelationName => function ($query) use ($fields) {
                    $query->select($fields);
                }];
            })
            ->toArray();

        $queryBuilder->with($withs);
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

    public function createOther(): array
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