<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

use function array_merge;
use function assert;
use function basename;
use function glob;
use function is_dir;
use function sprintf;

/**
 * @coversNothing
 */
final class PHPUnitTest extends FunctionalTestBase
{
    /**
     * @param array<string, string|bool> $options
     *
     * @dataProvider provideGithubIssues
     */
    public function testGithubIssues(
        string $directory,
        array $options,
        ?string $testPattern = null,
        ?string $assertionPattern = null
    ): void {
        $this->assertTestsPassed($this->invokeParatest(
            null,
            array_merge([
                '--configuration' => sprintf('%s%sphpunit%s.xml', $directory, DS, basename($directory)),
            ], $options),
        ), $testPattern, $assertionPattern);
    }

    /**
     * @return array<string, array<string, (string|array<string, (string|bool)>|null)>>
     */
    public function provideGithubIssues(): array
    {
        $directory = $this->fixture('github');
        $cases     = [];
        $glob      = glob($directory . DS . '*');
        assert($glob !== false);
        foreach ($glob as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $cases['issue-' . basename($path)] = [
                'directory' => $path,
                'options' => [],
                'testPattern' => null,
                'assertionPattern' => null,
            ];
        }

        $cases['issue-420bis']['options']['--bootstrap'] = sprintf(
            '%s%sbootstrap420bis.php',
            $cases['issue-420bis']['directory'],
            DS
        );

        $cases['issue-432']['options']          = ['--group' => 'group1'];
        $cases['issue-432']['testPattern']      = '1';
        $cases['issue-432']['assertionPattern'] = '1';

        $cases['issue-505']['options']       = ['--no-test-tokens' => true];
        $cases['issue-505tokens']['options'] = [];

        return $cases;
    }
}
