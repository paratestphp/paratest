<?php

/**
 * ProcessCallback.php
 *
 * @package    Atlas
 * @author     Christopher Biel <christopher.biel@jungheinrich.de>
 * @copyright  2013 Jungheinrich AG
 * @license    Proprietary license
 * @version    $Id$
 */
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