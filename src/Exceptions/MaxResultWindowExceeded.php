<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Exceptions\QueryLimitExceeded;

/**
 * The 400 `paginate()` throws for a page that ends past the result window:
 * Elasticsearch refuses a search whose `from + size` exceeds the index's
 * `max_result_window` (10000 by default).
 */
final class MaxResultWindowExceeded extends QueryLimitExceeded
{
    public const ERROR_CODE = 'max_result_window_exceeded';

    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $maxResultWindow,
        string $pageName = 'page',
    ) {
        parent::__construct(
            "Page {$page} of {$perPage} results ends past the first {$maxResultWindow} results, the most a page can reach.",
            self::ERROR_CODE,
            $pageName,
        );
    }
}
