<?php

declare(strict_types=1);

namespace Jackardios\ElasticQueryWizard\Enums;

enum BoolClause: string
{
    case FILTER = 'filter';
    case MUST = 'must';
    case SHOULD = 'should';
    case MUST_NOT = 'must_not';
}
