<?php

declare(strict_types=1);

namespace ParaTest\Tests\Unit\WrapperRunner;

use ParaTest\Tests\MemoryPrinter;
use ParaTest\WrapperRunner\ProgressPrinterOutput;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/** @internal */
#[CoversClass(ProgressPrinterOutput::class)]
final class ProgressPrinterOutputTest extends TestCase
{
    private MemoryPrinter $progress;
    private MemoryPrinter $output;
    private ProgressPrinterOutput $printer;

    protected function setUp(): void
    {
        $this->progress = new MemoryPrinter();
        $this->output   = new MemoryPrinter();

        $this->printer = new ProgressPrinterOutput(
            $this->progress,
            $this->output,
        );
    }

    public function testSkipProgressRelatedContents(): void
    {
        $this->printer->print("\n");
        $this->printer->print(' ');
        $this->printer->print('   ');
        $this->printer->print(' 65 / 75 ( 86%)');
        $this->printer->print(' 2484 / 2484 (100%)');

        self::assertSame('', $this->progress->tail());
        self::assertSame('', $this->output->tail());
    }

    public function testAProgressGoesIntoProgressTheRestInOutput(): void
    {
        foreach (['E', 'F', 'I', 'N', 'D', 'R', 'W', 'S', '.'] as $progress) {
            $this->printer->print($progress);
        }

        $this->printer->print('var_dump');

        self::assertSame('EFINDRWS.', $this->progress->tail());
        self::assertSame('var_dump', $this->output->tail());

        $this->printer->print('a ');
        self::assertSame('', $this->progress->tail());
        self::assertSame('a ', $this->output->tail());

        $this->printer->print(' z');
        self::assertSame('', $this->progress->tail());
        self::assertSame(' z', $this->output->tail());

        $this->printer->print('  65 / 75 ( 86%)');
        self::assertSame('', $this->progress->tail());
        self::assertSame('  65 / 75 ( 86%)', $this->output->tail());

        $this->printer->print(' 2484 / 2484 (100%) ');
        self::assertSame('', $this->progress->tail());
        self::assertSame(' 2484 / 2484 (100%) ', $this->output->tail());
    }

    public function testFlushBoth(): void
    {
        self::assertFalse($this->progress->hasBeenFlushed());
        self::assertFalse($this->output->hasBeenFlushed());

        $this->printer->flush();

        self::assertTrue($this->progress->hasBeenFlushed());
        self::assertTrue($this->output->hasBeenFlushed());
    }
}
