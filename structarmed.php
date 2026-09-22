<?php

declare(strict_types=1);

use Boundwize\StructArmed\Architecture;
use Boundwize\StructArmed\Preset\Preset;

return Architecture::define()
    ->withPresets(Preset::PSR4(), Preset::CODEQUALITY())
    ->layer('RunnerInterface', 'src/RunnerInterface.php')
    ->layer('Options', 'src/Options.php')
    ->layer('JUnit', 'src/JUnit')
    ->layer('TestDox', 'src/TestDox')
    ->layer('Util', 'src/Util')
    ->layerPattern(
        'WrapperRunner',
        '/^ParaTest\\\\WrapperRunner\\\\.*$/',
        '/^ParaTest\\\\WrapperRunner\\\\ShardDistribution$/'
    )
    ->layer('ShardDistribution', 'src/WrapperRunner/ShardDistribution.php')
    ->layer('Command', 'src/ParaTestCommand.php')
    ->ruleset([
        'RunnerInterface'   => [],
        'ShardDistribution' => [],
        'Options'           => ['RunnerInterface', 'ShardDistribution'],
        'JUnit'             => [],
        'TestDox'           => [],
        'Util'              => [],
        'WrapperRunner'     => ['+Options', 'JUnit', 'TestDox'],
        'Command'           => ['+WrapperRunner'],
    ]);
