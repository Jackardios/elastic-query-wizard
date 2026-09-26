<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Tests\Unit\Groups;

use Closure;
use Jackardios\ElasticQueryWizard\ElasticGroup;
use Jackardios\ElasticQueryWizard\Groups\AbstractElasticGroup;
use Jackardios\ElasticQueryWizard\Tests\UnitTestCase;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

/**
 * A filter group has no value of its own, so value modifiers are refused when it is configured.
 */
#[Group('unit')]
#[Group('group')]
class GroupValueModifierTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{Closure(): AbstractElasticGroup, Closure(AbstractElasticGroup): mixed, string}>
     */
    public static function modifiers(): iterable
    {
        $groups = [
            'bool' => fn (): AbstractElasticGroup => ElasticGroup::bool('advanced'),
            'nested' => fn (): AbstractElasticGroup => ElasticGroup::nested('comments'),
        ];
        $modifiers = [
            'default' => fn (AbstractElasticGroup $group) => $group->default('x'),
            'prepareValueWith' => fn (AbstractElasticGroup $group) => $group->prepareValueWith(fn ($value) => $value),
            'when' => fn (AbstractElasticGroup $group) => $group->when(fn () => true),
            'asBoolean' => fn (AbstractElasticGroup $group) => $group->asBoolean(),
        ];

        foreach ($groups as $groupName => $group) {
            foreach ($modifiers as $method => $modifier) {
                yield "{$groupName} {$method}" => [$group, $modifier, $method];
            }
        }
    }

    /**
     * @param  Closure(): AbstractElasticGroup  $group
     * @param  Closure(AbstractElasticGroup): mixed  $modifier
     */
    #[Test]
    #[DataProvider('modifiers')]
    public function a_value_modifier_is_refused(Closure $group, Closure $modifier, string $method): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("{$method}() does not apply to it; call it on a child filter.");

        $modifier($group());
    }
}
