<?php

declare(strict_types=1);

namespace ParaTest\Console\Testers;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * A base for Testers. A Tester is a specialized console
 * command for controlling a given tool - i.e PHPUnit
 */
abstract class Tester
{
    /**
     * Configures the ParaTestCommand with Tester specific
     * definitions.
     *
     * @return mixed
     */
    abstract public function configure(Command $command);

    abstract public function execute(InputInterface $input, OutputInterface $output): int;

    /**
     * Returns non-empty options.
     *
     * @return array<string, string>
     */
    protected function getOptions(InputInterface $input): array
    {
        $options = $input->getOptions();
        foreach ($options as $key => $value) {
            if (! empty($options[$key])) {
                continue;
            }

            unset($options[$key]);
        }

        return $options;
    }
}
