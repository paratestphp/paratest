<?php

declare(strict_types=1);

namespace ParaTest\Logging\JUnit;

use DOMDocument;
use DOMElement;
use ParaTest\Logging\LogInterpreter;

use function assert;
use function dirname;
use function file_put_contents;
use function htmlspecialchars;
use function is_dir;
use function mkdir;
use function sprintf;
use function str_replace;

use const ENT_XML1;

/** @internal */
final class Writer
{
    /**
     * The name attribute of the testsuite being
     * written.
     *
     * @var string
     */
    private $name;

    /** @var LogInterpreter */
    private $interpreter;

    /** @var DOMDocument */
    private $document;

    public function __construct(LogInterpreter $interpreter, string $name)
    {
        $this->name                   = $name;
        $this->interpreter            = $interpreter;
        $this->document               = new DOMDocument('1.0', 'UTF-8');
        $this->document->formatOutput = true;
    }

    /**
     * Get the name of the root suite being written.
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns the xml structure the writer
     * will use.
     */
    public function getXml(): string
    {
        $mainSuite = $this->interpreter->mergeReaders();
        if ($mainSuite->name === '') {
            $mainSuite->name = $this->name;
        }

        $xmlTestsuites = $this->document->createElement('testsuites');
        $xmlTestsuites->appendChild($this->createSuiteNode($mainSuite));
        $this->document->appendChild($xmlTestsuites);

        $xml = $this->document->saveXML();
        assert($xml !== false);

        return $xml;
    }

    /**
     * Write the xml structure to a file path.
     */
    public function write(string $path): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        file_put_contents($path, $this->getXml());
    }

    /**
     * Append a testsuite node to the given
     * root element.
     */
    private function createSuiteNode(TestSuite $parentSuite): DOMElement
    {
        $suiteNode = $this->document->createElement('testsuite');
        $suiteNode->setAttribute('name', $parentSuite->name);
        if ($parentSuite->file !== '') {
            $suiteNode->setAttribute('file', $parentSuite->file);
        }

        $suiteNode->setAttribute('tests', (string) $parentSuite->tests);
        $suiteNode->setAttribute('assertions', (string) $parentSuite->assertions);
        $suiteNode->setAttribute('errors', (string) ($parentSuite->errors + $parentSuite->risky));
        $suiteNode->setAttribute('warnings', (string) $parentSuite->warnings);
        $suiteNode->setAttribute('failures', (string) $parentSuite->failures);
        $suiteNode->setAttribute('skipped', (string) $parentSuite->skipped);
        $suiteNode->setAttribute('time', (string) $parentSuite->time);

        foreach ($parentSuite->suites as $suite) {
            $suiteNode->appendChild($this->createSuiteNode($suite));
        }

        foreach ($parentSuite->cases as $case) {
            $suiteNode->appendChild($this->createCaseNode($case));
        }

        return $suiteNode;
    }

    /**
     * Append a testcase node to the given testsuite
     * node.
     */
    private function createCaseNode(TestCase $case): DOMElement
    {
        $caseNode = $this->document->createElement('testcase');

        $caseNode->setAttribute('name', $case->name);
        $caseNode->setAttribute('class', $case->class);
        $caseNode->setAttribute('classname', str_replace('\\', '.', $case->class));
        $caseNode->setAttribute('file', $case->file);
        $caseNode->setAttribute('line', (string) $case->line);
        $caseNode->setAttribute('assertions', (string) $case->assertions);
        $caseNode->setAttribute('time', sprintf('%F', $case->time));

        if ($case instanceof TestCaseWithMessage) {
            if ($case instanceof SkippedTestCase) {
                $defectNode = $this->document->createElement($case->getXmlTagName());
            } else {
                $defectNode = $this->document->createElement($case->getXmlTagName(), htmlspecialchars($case->text, ENT_XML1));
                $type       = $case->type;
                if ($type !== null) {
                    $defectNode->setAttribute('type', $type);
                }
            }

            $caseNode->appendChild($defectNode);
        }

        return $caseNode;
    }
}
