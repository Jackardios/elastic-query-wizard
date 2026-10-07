<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Exceptions;

use Jackardios\QueryWizard\Exceptions\InvalidQuery;
use Symfony\Component\HttpFoundation\Response;

/**
 * The 400 `paginate()` throws for a page size or a page number below 1, which
 * the search builder refuses with an `InvalidArgumentException`.
 */
final class InvalidPagination extends InvalidQuery
{
    public const ERROR_CODE = 'invalid_pagination';

    public static function pageSize(int $perPage): self
    {
        return new self(Response::HTTP_BAD_REQUEST, "The page size must be at least 1, got {$perPage}.", errorCode: self::ERROR_CODE);
    }

    public static function page(int $page, string $pageName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            "The page must be at least 1, got {$page}.",
            errorCode: self::ERROR_CODE,
            parameter: $pageName,
        );
    }
}
