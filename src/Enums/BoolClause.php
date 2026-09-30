<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Enums;

enum BoolClause: string
{
    case Filter = 'filter';
    case Must = 'must';
    case Should = 'should';
    case MustNot = 'must_not';
}
