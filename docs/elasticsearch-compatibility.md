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

Use the `elasticsearch/elasticsearch` client of the server's major version. The 8.x client asks a 9.x server to accept
8.x requests, so a parameter removed in 9.x (such as `force_source`) only draws a deprecation warning through it; the
9.x client gets the error the tables below describe.
| 7.x and below | Not supported | Use older package versions |

## Elasticsearch 9.x Breaking Changes

If you're using or upgrading to Elasticsearch 9.x, be aware of these removed/changed features:

### Removed Features

| Feature | ES 8.x | ES 9.x | Impact |
|---------|--------|--------|--------|
| Range `from`/`to` params | Supported | Removed | Use `gt`/`gte`/`lt`/`lte` |
| `_knn_search` endpoint | Available | Removed | Use `knn` in `_search` |
| `force_source` highlighting | Supported | Removed | Remove this parameter |
| Frozen indices | Supported | Removed | Unfreeze before upgrade |

### Changed Behavior

| Feature | ES 8.x | ES 9.x | Recommendation |
|---------|--------|--------|----------------|
| `random_score` with a seed and no `field` | 400: reads `_id`, whose fielddata is disabled | Reads `_seq_no` | Nothing: `ElasticSort::random()` sends `field: _seq_no` with a seed |

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

A `random_score` with a seed needs a `field`. `ElasticSort::random()` uses `_seq_no` when you pass a seed, so the same
code works on 8.x and 9.x; call `field()` only to pick another field. Do not use `_id`: Elasticsearch refuses it for
`random_score`.

```php
ElasticQueryWizard::for(Post::class)
    ->allowedSorts([
        ElasticSort::random('shuffle')->seed($request->session()->getId()),
    ])
    ->build();
```

A `function_score` with `random_score` that you add yourself, for example through `tapSearchBuilder()`, must set `field`
the same way.

### Boolean Aggregations

A histogram on a boolean field works on Elasticsearch 8 and 9 (checked on 9.3 and 9.5), with the keys `0` and `1`. A
`terms` aggregation gives `true` and `false` keys instead:

```php
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
   - Direct `_knn_search` endpoint calls

### Code Changes Required

**Random sorting:** `ElasticSort::random()->seed()` needs no change, since it sets `field` to `_seq_no`. Add `field` to
any `random_score` you build yourself.

**Highlighting:**
```php
// Before (ES 8.x)
->tapSearchBuilder(fn($b) => $b->highlight('title', ['force_source' => true]))

// After (ES 8.x/9.x compatible)
->highlight('title')
```

### Testing

After making changes, run your application's tests against both ES versions if possible. This package's own suite
runs on both with `make test-es8` and `make test-es9` from its repository.

### Package Compatibility

This package handles most ES 8.x/9.x differences internally. The filters, sorts, and includes work identically on both versions. The main areas requiring attention are:

1. **Custom queries** via `tapSearchBuilder()` — Review for deprecated features
2. **Custom highlighting options** — Remove `force_source`
