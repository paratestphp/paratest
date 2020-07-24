<?php

declare(strict_types=1);

namespace ParaTest\Console;

use Symfony\Component\Process\Process;

use function file_exists;
use function file_get_contents;
use function is_array;
use function is_readable;
use function json_decode;
use function trim;

/**
 * Obtain version information of the ParaTest application itself based on
 * it's current installment (composer; git; default passed)
 */
final class VersionProvider
{
    private const PACKAGE = 'brianium/paratest';

    /** @var null */
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
        $cmd     = 'git describe --tags --always --first-parent';
        $process = Process::fromShellCommandline($cmd, __DIR__);
        if ($process->run() !== 0) {
            return null;
        }

        return trim($process->getOutput());
    }

    public function getComposerInstalledVersion(string $package): ?string
    {
        if (($path = $this->getComposerInstalledJsonPath()) === null) {
            return null;
        }

        $result = file_get_contents($path);
        if ($result === false) {
            return null;
        }

        $struct = json_decode($result, true, 16);
        if (! is_array($struct)) {
            return null;
        }

        foreach ($struct as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $name = $entry['name'] ?? null;
            if ($name === null || $name !== $package) {
                continue;
            }

            $version = $entry['version'] ?? null;
            if ($version === null) {
                continue;
            }

            return $version;
        }

        return null;
    }

    /**
     * @return string|null path to composer/installed.json
     */
    private function getComposerInstalledJsonPath(): ?string
    {
        $paths = [
            // path in the installed version
            __DIR__ . '/../../../../composer/installed.json',
            // path in the source version
            __DIR__ . '/../../vendor/composer/installed.json',
        ];

        // first hit makes it
        foreach ($paths as $path) {
            if (file_exists($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
