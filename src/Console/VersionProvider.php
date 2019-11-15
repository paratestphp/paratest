<?php

declare(strict_types=1);

namespace ParaTest\Console;

use Symfony\Component\Process\Process;

/**
 * Class VersionProvider.
 *
 * Obtain version information of the ParaTest application itself based on
 * it's current installment (composer; git; default passed)
 */
final class VersionProvider
{
    private const PACKAGE = 'brianium/paratest';

    /**
     * @var null
     */
    private $default;

    public function __construct($default = null)
    {
        $this->default = $default;
    }

    public static function getVersion($default = null)
    {
        $provider = new self($default);

        return $provider->getParaTestVersion();
    }

    public function getParaTestVersion()
    {
        return $this->getComposerInstalledVersion(self::PACKAGE)
            ?? $this->getGitVersion()
            ?? $this->default;
    }

    public function getGitVersion()
    {
        $cmd = 'git describe --tags --always --first-parent';
        $process = \method_exists(Process::class, 'fromShellCommandline') ?
            Process::fromShellCommandline($cmd, __DIR__) :
            new Process($cmd, __DIR__);

        if ($process->run() !== 0) {
            return null;
        }

        return \trim($process->getOutput());
    }

    public function getComposerInstalledVersion($package)
    {
        if (null === $path = $this->getComposerInstalledJsonPath()) {
            return;
        }

        $result = \file_get_contents($path);
        if (false === $result) {
            return;
        }

        $struct = \json_decode($result, true, 16);
        if (!\is_array($struct)) {
            return;
        }

        foreach ($struct as $entry) {
            if (!\is_array($entry)) {
                continue;
            }
            $name = $entry['name'] ?? null;
            if (null === $name || $name !== $package) {
                continue;
            }
            $version = $entry['version'] ?? null;
            if (null === $version) {
                continue;
            }

            return $version;
        }
    }

    /**
     * @return string|null path to composer/installed.json
     */
    private function getComposerInstalledJsonPath()
    {
        $paths = [
            // path in the installed version
            __DIR__ . '/../../../../composer/installed.json',
            // path in the source version
            __DIR__ . '/../../vendor/composer/installed.json',
        ];

        // first hit makes it
        foreach ($paths as $path) {
            if (\file_exists($path) && \is_readable($path)) {
                return $path;
            }
        }
    }
}
