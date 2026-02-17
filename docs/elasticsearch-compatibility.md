# Elasticsearch Version Compatibility

This package supports Elasticsearch 8.x and 9.x. This document covers version-specific behavior, breaking changes, and migration guidance.

## Table of Contents

- [Supported Versions](#supported-versions)
- [Elasticsearch 9.x Breaking Changes](#elasticsearch-9x-breaking-changes)
- [Safe Usage Examples](#safe-usage-examples)
- [Migration Guide: 8.x to 9.x](#migration-guide-8x-to-9x)

## Supported Versions

| Elasticsearch | Status | Notes |
|---------------|--------|-------|
| 8.x | Fully supported | Recommended for production |
| 9.x | Fully supported | Some features removed (see below) |
| 7.x and below | Not supported | Use older package versions |

## Elasticsearch 9.x Breaking Changes

If you're using or upgrading to Elasticsearch 9.x, be aware of these removed/changed features:

### Removed Features

| Feature | ES 8.x | ES 9.x | Impact |
|---------|--------|--------|--------|
| Range `from`/`to` params | Supported | Removed | Use `gt`/`gte`/`lt`/`lte` |
| `_knn_search` endpoint | Available | Removed | Use `knn` in `_search` |
| `force_source` highlighting | Supported | Removed | Remove this parameter |
| Boolean histogram aggregation | Works | Error | Use `terms` aggregation |
| Frozen indices | Supported | Removed | Unfreeze before upgrade |

### Changed Behavior

| Feature | ES 8.x | ES 9.x | Recommendation |
|---------|--------|--------|----------------|
| `random_score` default field | `_id` | `_seq_no` | Specify field explicitly |

## Safe Usage Examples

### Range Filter

The package already uses ES 9.x compatible operators:

```php
// Correct: uses gte/lte (works on both ES 8.x and 9.x)
ElasticFilter::range('price')

// Query parameters
// ?filter[price][gte]=100&filter[price][lte]=500
```

### Random Sorting

```php
// Option 1: ElasticSort::random() with explicit field
ElasticQueryWizard::for(Post::class)
    ->allowedSorts([
        ElasticSort::random('shuffle')->seed(12345)->field('_seq_no'),
    ])
    ->build();

// Option 2: tapSearchBuilder with functionScore
ElasticQueryWizard::for(Post::class)
    ->tapSearchBuilder(function ($builder) {
        $builder->must(
            Query::functionScore()
                ->addFunction([
                    'random_score' => [
                        'seed' => 12345,
                        'field' => '_seq_no',  // Explicit for consistency
                    ],
                ])
                ->boostMode('replace')
        );
    })
    ->build();
```

### Boolean Aggregations

```php
// Wrong: histogram on boolean field (fails on ES 9.x)
// ->aggregate('by_active', ElasticAggregation::histogram('is_active', 1))

// Correct: use terms aggregation
->aggregate('by_active', ElasticAggregation::terms('is_active'))
```

### Highlighting

```php
// Wrong: force_source option (fails on ES 9.x)
->tapSearchBuilder(function ($builder) {
    $builder->highlight('title', [
        'force_source' => true,  // Remove this
    ]);
})

// Correct: highlight without force_source
->highlight('title')
->highlight('body')
```

## Migration Guide: 8.x to 9.x

### Before Upgrading

1. **Check for frozen indices** — Unfreeze all frozen indices:
   ```bash
   # List frozen indices
   curl -X GET "localhost:9200/_cat/indices?v&h=index,status&s=index:desc" | grep frozen

   # Unfreeze an index
   curl -X POST "localhost:9200/my_index/_unfreeze"
   ```

2. **Audit your code** for these patterns:
   - `random_score` without explicit `field`
   - `force_source` in highlight options
   - Histogram aggregations on boolean fields
   - Direct `_knn_search` endpoint calls

### Code Changes Required

**Random sorting:**
```php
// Before (ES 8.x)
ElasticSort::random('shuffle')->seed(12345)

// After (ES 8.x/9.x compatible)
ElasticSort::random('shuffle')->seed(12345)->field('_seq_no')
```

**Boolean aggregations:**
```php
// Before (ES 8.x)
->aggregate('active', ElasticAggregation::histogram('is_active', 1))

// After (ES 8.x/9.x compatible)
->aggregate('active', ElasticAggregation::terms('is_active'))
```

**Highlighting:**
```php
// Before (ES 8.x)
->tapSearchBuilder(fn($b) => $b->highlight('title', ['force_source' => true]))

// After (ES 8.x/9.x compatible)
->highlight('title')
```

### Testing

After making changes, test your application against both ES versions if possible:

```bash
# Test with ES 8.x
make test-es8

# Test with ES 9.x
make test-es9
```

### Package Compatibility

This package handles most ES 8.x/9.x differences internally. The filters, sorts, and includes work identically on both versions. The main areas requiring attention are:

1. **Custom queries** via `tapSearchBuilder()` — Review for deprecated features
2. **Direct aggregations** via `aggregate()` — Check for histogram on booleans
3. **Custom highlighting options** — Remove `force_source`
