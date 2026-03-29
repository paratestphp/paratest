<?php

declare(strict_types=1);

namespace ParaTest\WrapperRunner;

/** @internal */
enum ShardDistribution: string
{
    case Sequential = 'sequential';
    case RoundRobin = 'round-robin';
}
