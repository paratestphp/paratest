<?php

declare(strict_types=1);

namespace ParaTest\Runners\PHPUnit;

/**
 * Class Configuration.
 *
 * Stores information about the phpunit xml
 * configuration being used to run tests
 */
class Configuration
{
    /**
     * Path to the configuration file.
     *
     * @var string
     */
    protected $path;

    /**
     * @var \SimpleXMLElement
     */
    protected $xml;

    protected $availableNodes = ['exclude', 'file', 'directory', 'testsuite'];

    /**
     * A collection of datastructures
     * build from the <testsuite> nodes inside of a
     * PHPUnit configuration.
     *
     * @var array
     */
    protected $suites = [];

    public function __construct(string $path)
    {
        $this->path = $path;
        if (\file_exists($path)) {
            $this->xml = \simplexml_load_string(\file_get_contents($path));
        }
    }

    /**
     * Converting the configuration to a string
     * returns the configuration path.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->path;
    }

    /**
     * Get the bootstrap PHPUnit configuration attribute.
     *
     * @return string The bootstrap attribute or empty string if not set
     */
    public function getBootstrap(): string
    {
        if ($this->xml) {
            return (string) $this->xml->attributes()->bootstrap;
        }

        return '';
    }

    /**
     * Returns the path to the phpunit configuration
     * file.
     *
     * @return string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Return the contents of the <testsuite> nodes
     * contained in a PHPUnit configuration.
     *
     * @return SuitePath[][]|null
     */
    public function getSuites()
    {
        if (!$this->xml) {
            return;
        }
        $suites = [];
        $nodes = $this->xml->xpath('//testsuites/testsuite');

        foreach ($nodes as $node) {
            $suites = \array_merge_recursive($suites, $this->getSuiteByName((string) $node['name']));
        }

        return $suites;
    }

    public function hasSuites()
    {
        return !empty($this->getSuitesName());
    }

    public function getSuitesName()
    {
        if (!$this->xml) {
            return;
        }
        $nodes = $this->xml->xpath('//testsuites/testsuite');
        $names = [];
        foreach ($nodes as $node) {
            $names[] = (string) $node['name'];
        }

        return $names;
    }

    /**
     * Return the contents of the <testsuite> nodes
     * contained in a PHPUnit configuration.
     *
     * @param string $suiteName
     *
     * @return SuitePath[]|null
     */
    public function getSuiteByName(string $suiteName)
    {
        $nodes = $this->xml->xpath(\sprintf('//testsuite[@name="%s"]', $suiteName));

        $suites = [];
        $excludedPaths = [];
        foreach ($nodes as $node) {
            foreach ($this->availableNodes as $nodeName) {
                foreach ($node->{$nodeName} as $nodeContent) {
                    switch ($nodeName) {
                        case 'exclude':
                            foreach ($this->getSuitePaths((string) $nodeContent) as $excludedPath) {
                                $excludedPaths[$excludedPath] = $excludedPath;
                            }
                            break;
                        case 'testsuite':
                            $suites = \array_merge_recursive($suites, $this->getSuiteByName((string) $nodeContent));
                            break;
                        case 'directory':
                            // Replicate behaviour of PHPUnit
                            // if a directory is included and excluded at the same time, then it is considered included
                            foreach ($this->getSuitePaths((string) $nodeContent) as $dir) {
                                if (\array_key_exists($dir, $excludedPaths)) {
                                    unset($excludedPaths[$dir]);
                                }
                            }
                            // no break on purpose
                        default:
                            foreach ($this->getSuitePaths((string) $nodeContent) as $path) {
                                $suites[(string) $node['name']][] = new SuitePath(
                                    $path,
                                    $excludedPaths,
                                    (string) $nodeContent->attributes()->suffix
                                );
                            }
                            break;
                    }
                }
            }
        }

        return $suites;
    }

    /**
     * Return the path of the directory
     * that contains the phpunit configuration.
     *
     * @return string
     */
    public function getConfigDir(): string
    {
        return \dirname($this->path) . \DIRECTORY_SEPARATOR;
    }

    /**
     * Returns a suite paths relative to the config file.
     *
     * @param $path
     *
     * @return array|string[]
     */
    public function getSuitePaths(string $path)
    {
        $real = \realpath($this->getConfigDir() . $path);

        if ($real !== false) {
            return [$real];
        }

        if ($this->isGlobRequired($path)) {
            $paths = [];
            foreach (\glob($this->getConfigDir() . $path, GLOB_ONLYDIR) as $path) {
                if (($path = \realpath($path)) !== false) {
                    $paths[] = $path;
                }
            }

            return $paths;
        }

        throw new \RuntimeException("Suite path $path could not be found");
    }

    /**
     * Get override environment variables from phpunit config file.
     *
     * @return array
     */
    public function getEnvironmentVariables(): array
    {
        if (!isset($this->xml->php->env)) {
            return [];
        }

        $variables = [];

        foreach ($this->xml->php->env as $env) {
            $variables[(string) $env['name']] = (string) $env['value'];
        }

        return $variables;
    }

    /**
     * Returns true if path needs globbing (like a /path/*-to/string).
     *
     * @param string $path
     *
     * @return bool
     */
    public function isGlobRequired(string $path): bool
    {
        return \strpos($path, '*') !== false;
    }
}
