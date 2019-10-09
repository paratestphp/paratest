<?php

declare(strict_types=1);

namespace ParaTest\Tests\Functional;

class ProcessCallback
{
    protected $type;
    protected $buffer;

    public function callback($type, $buffer)
    {
        $this->type = $type;
        $this->buffer = $buffer;
    }

    /**
     * @return mixed
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return mixed
     */
    public function getBuffer()
    {
        return $this->buffer;
    }
}
