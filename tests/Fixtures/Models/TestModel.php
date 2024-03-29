<?php

namespace Jackardios\ElasticQueryWizard\Tests\Fixtures\Models;

use Elastic\ScoutDriverPlus\Searchable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class TestModel extends Model
{
    use Searchable;

    protected $guarded = [];

    public function relatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }

    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(RelatedModel::class);
    }

    public function otherRelatedModels(): HasMany
    {
        return $this->hasMany(RelatedModel::class);
    }

    public function relatedThroughPivotModels(): BelongsToMany
    {
        return $this->belongsToMany(RelatedThroughPivotModel::class, 'pivot_models');
    }

    public function relatedThroughPivotModelsWithPivot(): BelongsToMany
    {
        return $this->belongsToMany(RelatedThroughPivotModel::class, 'pivot_models')
            ->withPivot(['location']);
    }

    public function morphModels(): MorphMany
    {
        return $this->morphMany(MorphModel::class, 'parent');
    }

    public function scopeNamed(Builder $query, string $name): Builder
    {
        return $query->where('name', $name);
    }

    public function scopeCategorized(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeUser(Builder $query, self $user): Builder
    {
        return $query->where('id', $user->id);
    }

    public function scopeUserInfo(Builder $query, self $user, string $name): Builder
    {
        return $query
            ->where('id', $user->id)
            ->where('name', $name);
    }

    public function scopeCreatedBetween(Builder $query, $from, $to): Builder
    {
        return $query->whereBetween('created_at', [
            Carbon::parse($from), Carbon::parse($to),
        ]);
    }
}
