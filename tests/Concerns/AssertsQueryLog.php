<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Concerns;

use Illuminate\Support\Facades\DB;
use Jackardios\ElasticQueryWizard\Tests\TestCase;

/**
 * @mixin TestCase
 */
trait AssertsQueryLog
{
    protected function assertQueryLogContains(string $partialSql): void
    {
        $queryLog = collect(DB::getQueryLog())->pluck('query')->implode('|');

        $this->assertStringContainsString($partialSql, $queryLog);
    }

    protected function assertQueryLogDoesntContain(string $partialSql): void
    {
        $queryLog = collect(DB::getQueryLog())->pluck('query')->implode('|');

        $this->assertStringNotContainsString($partialSql, $queryLog, "Query log contained partial SQL: `{$partialSql}`");
    }
}
