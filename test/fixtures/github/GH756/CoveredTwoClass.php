<?php

declare(strict_types=1);

namespace ParaTest\Tests\fixtures\github\GH756;

final class CoveredTwoClass
{
    public function m(): bool
    {
        return $this->n();
    }

    private function n(): bool
    {
        return true;
    }
}
