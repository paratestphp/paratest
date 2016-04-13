<?php
namespace ParaTest\Runners\PHPUnit;

/**
 * Class Options
 *
 * An object containing all configurable information used
 * to run PHPUnit via ParaTest
 *
 * @package ParaTest\Runners\PHPUnit
 */
class Options
{
    /**
     * The number of processes to run at a time
     *
     * @var int
     */
    protected $processes;

    /**
     * The test path pointing to tests that will
     * be run
     *
     * @var string
     */
    protected $path;

    /**
     * The path to the PHPUnit binary that will be run
     *
     * @var string
     */
    protected $phpunit;

    /**
     * Determines whether or not ParaTest runs in
     * functional mode. If enabled, ParaTest will run
     * every test method in a separate process
     *
     * @var string
     */
    protected $functional;

    /**
     * Prevents starting new tests after a test has failed.
     *
     * @var boolean
     */
    protected $stopOnFailure;

    /**
     * A collection of post-processed option values. This is the collection
     * containing ParaTest specific options
     *
     * @var array
     */
    protected $filtered;

    /**
     * A collection of option values directly corresponding
     * to certain annotations - i.e group
     *
     * @var array
     */
    protected $annotations = array();

    public function __construct($opts = array())
    {
        foreach (self::defaults() as $opt => $value) {
            $opts[$opt] = isset($opts[$opt]) ? $opts[$opt] : $value;
        }

        $this->processes = $opts['processes'];
        $this->path = $opts['path'];
        $this->phpunit = $opts['phpunit'];
        $this->functional = $opts['functional'];
        $this->stopOnFailure = $opts['stop-on-failure'];
        $this->runner = $opts['runner'];
        $this->noTestTokens = $opts['no-test-tokens'];
        $this->colors = $opts['colors'];
        $this->testsuite = $opts['testsuite'];
        $this->maxBatchSize = $opts['max-batch-size'];
        $this->filter = $opts['filter'];

        // we need to register that options if they are blank but do not get them as
        // key with null value in $this->filtered as it will create problems for
        // phpunit command line generation (it will add them in command line with no value
        // and it's wrong because group and exclude-group options require value when passed
        // to phpunit)
        $this->groups = isset($opts['group']) && $opts['group'] !== ""
                      ? explode(",", $opts['group'])
                      : array();
        $this->excludeGroups = isset($opts['exclude-group']) && $opts['exclude-group'] !== ""
                             ? explode(",", $opts['exclude-group'])
                             : array();

        if (strlen($opts['filter']) > 0 && !$this->functional) {
            throw new \RuntimeException("Option --filter is not implemented for non functional mode");
        }

        $this->filtered = $this->filterOptions($opts);
        $this->initAnnotations();
    }

    /**
     * Public read accessibility
     *
     * @param $var
     * @return mixed
     */
    public function __get($var)
    {
        return $this->$var;
    }

    /**
     * Returns a collection of ParaTest's default
     * option values
     *
     * @return array
     */
    protected static function defaults()
    {
        return array(
            'processes' => 5,
            'path' => '',
            'phpunit' => static::phpunit(),
            'functional' => false,
            'stop-on-failure' => false,
            'runner' => 'Runner',
            'no-test-tokens' => false,
            'colors' => false,
            'testsuite' => '',
            'max-batch-size' => 0,
            'filter' => null
        );
    }

    /**
     * Get the path to phpunit
     * First checks if a Windows batch script is in the composer vendors directory.
     * Composer automatically handles creating a .bat file, so if on windows this should be the case.
     * Second look for the phpunit binary under nix
     * Defaults to phpunit on the users PATH
     * @return string $phpunit the path to phpunit
     */
    protected static function phpunit()
    {
        $vendor  = static::vendorDir();
        $phpunit = $vendor . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit';
        $batch = $phpunit . '.bat';

        if (file_exists($batch)) {
            return $batch;
        }

        if (file_exists($phpunit)) {
            return $phpunit;
        }

        return 'phpunit';
    }

    /**
     * Get the path to the vendor directory
     * First assumes vendor directory is accessible from src (i.e development)
     * Second assumes vendor directory is accessible within src
     */
    protected static function vendorDir()
    {
        $vendor = dirname(dirname(dirname(dirname(__DIR__)))) . DIRECTORY_SEPARATOR . 'vendor';
        if (!file_exists($vendor)) {
            $vendor = dirname(dirname(dirname(dirname(dirname(dirname(__DIR__))))));
        }

        return $vendor;
    }

    /**
     * Filter options to distinguish between paratest
     * internal options and any other options
     * @param  array $options
     * @return array
     */
    protected function filterOptions($options)
    {
        $filtered = array_diff_key($options, array(
            'processes' => $this->processes,
            'path' => $this->path,
            'phpunit' => $this->phpunit,
            'functional' => $this->functional,
            'stop-on-failure' => $this->stopOnFailure,
            'runner' => $this->runner,
            'no-test-tokens' => $this->noTestTokens,
            'colors' => $this->colors,
            'testsuite' => $this->testsuite,
            'max-batch-size' => $this->maxBatchSize,
            'filter' => $this->filter
        ));
        if ($configuration = $this->getConfigurationPath($filtered)) {
            $filtered['configuration'] = new Configuration($configuration);
        }

        return $filtered;
    }

    /**
     * Take an array of filtered options and return a
     * configuration path
     *
     * @param $filtered
     * @return string|null
     */
    protected function getConfigurationPath($filtered)
    {
        if (isset($filtered['configuration'])) {
            return $this->getDefaultConfigurationForPath($filtered['configuration'], $filtered['configuration']);
        }
        return $this->getDefaultConfigurationForPath();
    }

    /**
     * Retrieve the default configuration given a path (directory or file).
     * This will search into the directory, if a directory is specified
     *
     * @param string $path The path to search into
     * @param string $default The default value to give back
     * @return string|null
     */
    private function getDefaultConfigurationForPath($path = '.', $default = null)
    {
        if ($this->isFile($path)) {
            return realpath($path);
        }

        $path = rtrim($path, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $suffixes = array('phpunit.xml', 'phpunit.xml.dist');

        foreach ($suffixes as $suffix) {
            if ($this->isFile($path . $suffix)) {
                return realpath($path . $suffix);
            }
        }
        return $default;
    }

    /**
     * Load options that are represented by annotations
     * inside of tests i.e @group group1 = --group group1
     */
    protected function initAnnotations()
    {
        $annotatedOptions = array('group');
        foreach ($this->filtered as $key => $value) {
            if (array_search($key, $annotatedOptions) !== false) {
                $this->annotations[$key] = $value;
            }
        }
    }

    /**
     * @param $file
     * @return bool
     */
    private function isFile($file)
    {
        return file_exists($file) && !is_dir($file);
    }
}
